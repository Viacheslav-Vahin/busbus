<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Bus;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;

class TicketSalesReport extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $title = 'Звіт по продажах квитків';
    protected static string $view = 'filament.pages.ticket-sales-report';

    // значення фільтрів (публічні — щоб Livewire бачив)
    public ?string $from_date = null;
    public ?string $to_date   = null;
    public ?int    $bus_id    = null;

    /** Форма фільтрів */
    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            DatePicker::make('from_date')
                ->label('Від')
                ->native(false)
                ->live()                      // << оновлює таблицю
                ->afterStateUpdated(fn () => $this->resetTable()),

            DatePicker::make('to_date')
                ->label('До')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->resetTable()),

            Select::make('bus_id')
                ->label('Автобус')
                ->options(fn () => Bus::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->native(false)
                ->placeholder('Всі автобуси')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTable()),
        ]);
    }

    /** Таблиця */
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(fn () => $this->getQuery())
            ->columns([
                TextColumn::make('date')
                    ->label('Дата')
                    ->date('MMM d, Y')
                    ->sortable(),

                TextColumn::make('route_display')   // accessor з Booking
                ->label('Маршрут')
                    ->sortable()
                    ->wrap(),

                TextColumn::make('bus.name')
                    ->label('Автобус')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('selected_seat')
                    ->label('Місце')
                    ->sortable(),

                TextColumn::make('passengerNames')  // accessor з Booking
                ->label('Пасажир')
                    ->wrap(),

                TextColumn::make('price')
                    ->label('Сума')
                    ->money('UAH')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Експорт PDF')
                    ->action(fn () => $this->exportPdf()),

                Tables\Actions\Action::make('export_csv')
                    ->label('Експорт CSV')
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    /** Базовий запит із фільтрами */
    protected function getQuery(): Builder
    {
        return Booking::query()
            ->with(['bus','route','user']) // важливо для PDF
            ->when($this->from_date, fn ($q) => $q->whereDate('date', '>=', $this->from_date))
            ->when($this->to_date, fn ($q) => $q->whereDate('date', '<=', $this->to_date))
            ->when($this->bus_id, fn ($q) => $q->where('bus_id', $this->bus_id))
            // якщо треба тільки оплачені:
            ->when(
                Schema::hasColumn('bookings', 'status'),
                fn ($q) => $q->where('status', 'paid')
            )
            ->orderBy('date');
    }

    /** Експорт PDF */
    public function exportPdf()
    {
        $bookings = $this->getQuery()->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ])->loadView('reports.ticket-sales-pdf', compact('bookings'));

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'ticket_sales.pdf'
        );
    }

    /** Експорт CSV (UTF‑8 з BOM, щоб Excel відкривав українську) */
    public function exportCsv()
    {
        $bookings = $this->getQuery()->get();

        $rows = [];
        $rows[] = ['Дата', 'Маршрут', 'Автобус', 'Місце', 'Пасажир', 'Сума'];

        foreach ($bookings as $b) {
            $rows[] = [
                $b->date instanceof \Carbon\Carbon ? $b->date->format('Y-m-d') : (string)$b->date,
                $b->route_display ?? optional($b->route)->name ?? '',
                optional($b->bus)->name ?? '',
                $b->selected_seat ?? $b->seat_number ?? '',
                $b->passengerNames ?? optional($b->user)->name ?? '',
                number_format((float)$b->price, 2, '.', ''),
            ];
        }

        $fh = fopen('php://temp', 'w+');
        // BOM для підтримки кирилиці
        fwrite($fh, "\xEF\xBB\xBF");

        foreach ($rows as $r) {
            fputcsv($fh, $r, ',');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ticket_sales.csv"',
        ]);
    }

    public function getTotals(): array
    {
        $q = $this->getQuery()->clone(); // щоб не мутував основний
        return [
            'count' => (clone $q)->count(),
            'sum'   => (clone $q)->sum('price'),
        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return '📃 Звіти по продажах';
    }
}
