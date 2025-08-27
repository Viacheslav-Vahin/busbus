<?php
// app/Http/Controllers/PaymentReturnController.php
namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PaymentReturnController extends Controller
{
    /**
     * WayForPay може прислати POST на /payment/return (returnUrl).
     * У такому випадку просто редіректимо на GET-сторінку з параметром order.
     * На GET віддаємо простий JSON зі станом оплати.
     */
    public function show(Request $r)
    {
        if ($r->isMethod('post')) {
            // WFP часто шле orderReference у POST-тілі
            $data  = $this->parseWfpPayload($r);
            $order = $r->input('orderReference')
                ?: $r->input('order')
                    ?: ($data['orderReference'] ?? null);

            return redirect()->route('payment.return', ['order' => $order]);
        }

        Log::info('RETURN-HIT', ['method' => $r->method(), 'all' => $r->all()]);

        $order    = $r->query('order') ?? $r->input('order') ?? $r->input('orderReference');
        $bookings = $order ? Booking::where('order_id', $order)->get() : collect();
        $paid     = $bookings->isNotEmpty() && $bookings->every(fn ($b) => $b->status === 'paid');

//        return response()->json([
//            'ok'    => true,
//            'order' => $order,
//            'paid'  => $paid,
//            'count' => $bookings->count(),
//        ]);
        return view('payments.return', [
            'order'  => $order,
            'paid'   => $paid,
            'count'  => $bookings->count(),
            'tickets'=> $bookings->pluck('ticket_uuid')->filter()->all(),
        ]);
    }

    /**
     * Технічний вебхук від WayForPay (serviceUrl). Тут оновлюємо бронювання.
     */
    public function webhook(Request $r)
    {
        $data = $this->parseWfpPayload($r);

        Log::build(['driver' => 'single', 'path' => storage_path('logs/payments.log')])
            ->info('WEBHOOK-HIT', [
                'ct'  => $r->header('content-type'),
                'all' => $data,
            ]);

        if (!$this->verifySignature($data)) {
            return response()->json(['reason' => 'bad signature'], 400);
        }

        $order = $data['orderReference'] ?? null;
        if (!$order) {
            return response()->json(['reason' => 'no orderReference'], 400);
        }

        try {
            DB::transaction(function () use ($order, $data) {
                $statusOk = ($data['transactionStatus'] ?? '') === 'Approved';
                $bookings = Booking::where('order_id', $order)->lockForUpdate()->get();

                foreach ($bookings as $b) {
                    $b->status = $statusOk ? 'paid' : 'failed';
                    if ($statusOk) {
                        $b->paid_at = now();
                    }

                    $pm                 = (array)($b->payment_meta ?? []);
                    $pm['wayforpay']    = $data;
                    $b->payment_meta    = $pm;

                    if ($statusOk && empty($b->ticket_uuid)) {
                        $b->ticket_uuid = (string) Str::uuid();
                    }

                    $b->save();

                    // Присвоїти роль пасажира (створимо, якщо ще нема)
                    if ($b->user && method_exists($b->user, 'assignRole')) {
                        try {
                            Role::findOrCreate('passenger', 'web');
                            $b->user->assignRole('passenger');
                        } catch (\Throwable $e) {
                            Log::warning('ROLE-ASSIGN-FAIL', ['msg' => $e->getMessage(), 'user' => $b->user_id]);
                        }
                    }

                    // Лист із посиланням на PDF-квиток
                    if ($statusOk && $b->user?->email) {
                        try {
                            $pdfUrl = route('tickets.pdf', ['uuid' => $b->ticket_uuid]);
                            Mail::raw(
                                "Дякуємо за оплату!\nКвиток: {$pdfUrl}",
                                fn ($m) => $m->to($b->user->email)->subject('MaxBus --- Ваш квиток')
                            );
                        } catch (\Throwable $e) {
                            Log::warning('MAIL-FAIL', ['e' => $e->getMessage(), 'booking' => $b->id]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::build(['driver' => 'single', 'path' => storage_path('logs/payments.log')])
                ->error('WEBHOOK-FAIL', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['status' => 'ok', 'orderReference' => $order]);
    }

    /**
     * Перевірка підпису відповіді WayForPay.
     */
    protected function verifySignature(array $p): bool
    {
        $fields = [
            $p['merchantAccount']   ?? '',
            $p['orderReference']    ?? '',
            $p['amount']            ?? '',
            $p['currency']          ?? '',
            $p['authCode']          ?? '',
            $p['cardPan']           ?? '',
            $p['transactionStatus'] ?? '',
            $p['reasonCode']        ?? '',
        ];

        $expected = hash_hmac('md5', implode(';', $fields), env('WAYFORPAY_MERCHANT_SECRET'));

        return isset($p['merchantSignature']) && hash_equals($expected, $p['merchantSignature']);
    }

    /**
     * Дружній до кривих заголовків парсер: підхоплює випадок,
     * коли все тіло прийшло як один JSON-рядок без правильного Content-Type.
     */
    private function parseWfpPayload(Request $r): array
    {
        // 1) Звичайний шлях
        $data = $r->all();

        // 2) Якщо прийшов "один ключ із JSON-рядком"
        if (count($data) === 1) {
            $onlyKey = array_key_first($data);
            if (is_string($onlyKey) && str_starts_with(ltrim($onlyKey), '{')) {
                $decoded = json_decode($onlyKey, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
        }

        // 3) Якщо було порожньо — спробуємо сирий body як JSON
        if (empty($data)) {
            $raw     = $r->getContent();
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        return is_array($data) ? $data : [];
    }
}
