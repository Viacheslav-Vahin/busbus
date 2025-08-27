<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// BaconQrCode v2 (GD backend)
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class TicketService
{
    /**
     * Створює (або знаходить) актуальні QR+PDF. Повертає [qrPath, pdfPath].
     */
    public function build(Booking $b): array
    {
        $b->loadMissing(['bus', 'route', 'user', 'currency']);

        // 0) Гарантуємо UUID квитка
        if (empty($b->ticket_uuid)) {
            $b->ticket_uuid = (string) Str::uuid();
        }

        // 1) Рахуємо "ревізію" з факторів, що впливають на вигляд квитка
        $sigPayload = [
            'uuid'    => $b->ticket_uuid,
            'route'   => [$b->route_id, $b->bus_id, $b->seat_number],
            'price'   => [$b->price, $b->currency_code],
            'status'  => $b->status,
            'updated' => (string) $b->updated_at,
        ];
        $rev = substr(hash('sha256', json_encode($sigPayload, JSON_UNESCAPED_UNICODE)), 0, 12);

        $qrPath  = "tickets/qr/{$b->ticket_uuid}-{$rev}.png";
        $pdfPath = "tickets/pdf/{$b->ticket_uuid}-{$rev}.pdf";

        // Якщо такі файли вже є — повертаємо їх, при потребі оновивши поля в БД
        if (Storage::disk('public')->exists($qrPath) && Storage::disk('public')->exists($pdfPath)) {
            if ($b->ticket_rev !== $rev || $b->qr_path !== $qrPath || $b->ticket_pdf_path !== $pdfPath) {
                $b->forceFill([
                    'ticket_rev'      => $rev,
                    'qr_path'         => $qrPath,
                    'ticket_pdf_path' => $pdfPath,
                ])->save();
            }
            return [$qrPath, $pdfPath];
        }

        // 2) Готуємо payload для QR (base64(JSON) + короткий HMAC підпис)
        $sig       = hash_hmac('sha256', $b->ticket_uuid . '|' . $rev, config('app.key'));
        $payload   = [
            't' => 'T',                    // тип (на майбутнє)
            'u' => $b->ticket_uuid,        // uuid квитка
            'r' => $rev,                   // ревізія
            's' => substr($sig, 0, 16),    // короткий підпис
        ];
        $qrPayload = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        // 3) Пробуємо згенерувати PNG локально (GD backend, без imagick)
        $png = null;
        try {
            if (class_exists(\BaconQrCode\Renderer\Image\GdImageBackEnd::class) && extension_loaded('gd')) {
                $renderer = new ImageRenderer(
                    new RendererStyle(520, 0),
                    new \BaconQrCode\Renderer\Image\GdImageBackEnd()
                );
                $writer = new Writer($renderer);
                $png    = $writer->writeString($qrPayload); // binary PNG
            } elseif (class_exists(\BaconQrCode\Renderer\Image\Png::class) && extension_loaded('gd')) {
                // Фолбек для старіших версій bacon/bacon-qr-code
                $renderer = new \BaconQrCode\Renderer\Image\Png();
                $renderer->setWidth(520);
                $renderer->setHeight(520);
                $renderer->setMargin(0);
                $writer = new Writer($renderer);
                $png    = $writer->writeString($qrPayload);
            }
        } catch (\Throwable $e) {
            // Замовчуємо — перейдемо на віддалений генератор
        }

        // 4) Якщо локально не вдалося — віддалений сервіс (без розширень)
        if (!$png) {
            $remote = 'https://api.qrserver.com/v1/create-qr-code/?size=520x520&margin=0&data='
                . rawurlencode($qrPayload);
            $png = @file_get_contents($remote);
            if (!$png) {
                throw new \RuntimeException('Не вдалося згенерувати QR: ані локально (GD), ані через віддалений сервіс.');
            }
        }

        // 5) Зберігаємо QR та готуємо data URI
        Storage::disk('public')->put($qrPath, $png);
        $qrDataUri = 'data:image/png;base64,' . base64_encode($png);

        // 6) Генеруємо PDF (вбудований <img src="data:...">, без SVG / без remote)
        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ])
            ->loadView('tickets.pdf', [
                'b'         => $b,
                'qrDataUri' => $qrDataUri,
                'company'   => \App\Models\CompanyProfile::first(),
            ])
            ->setPaper('a4', 'portrait');

        Storage::disk('public')->put($pdfPath, $pdf->output());

        // 7) Прибираємо попередні файли ревізій, якщо були
        if ($b->qr_path && $b->qr_path !== $qrPath) {
            Storage::disk('public')->delete([$b->qr_path, $b->ticket_pdf_path]);
        }

        // 8) Фіксуємо нову ревізію в БД
        $b->forceFill([
            'ticket_rev'      => $rev,
            'qr_path'         => $qrPath,
            'ticket_pdf_path' => $pdfPath,
        ])->save();

        return [$qrPath, $pdfPath];
    }
}
