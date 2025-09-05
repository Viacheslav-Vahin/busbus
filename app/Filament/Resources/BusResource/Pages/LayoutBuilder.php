<?php

namespace App\Filament\Resources\BusResource\Pages;

use App\Filament\Resources\BusResource;
use App\Models\{Bus, BusSeat, BusLayoutElement, SeatType};
use App\Services\BusLayoutSyncer;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;

class LayoutBuilder extends Page
{
    protected static string $resource = BusResource::class;
    protected static string $view = 'filament.resources.bus-resource.pages.layout-builder';

    public ?int $record = null; // bus_id

    public array $seats = [];
    public array $elements = [];
    public array $seatTypes = [];

    // ✨ ДОДАНО: розміри сітки динамічно
    public int $cols = 24;
    public int $rows = 8;

    public function mount(int|string $record): void
    {
        $this->record = (int) $record;

        $this->loadSeats();
        $this->loadElements();
        $this->recalcGrid(); // ✨

        $this->seatTypes = SeatType::select('id','name','code')
            ->orderBy('id')->get()->toArray();
    }

    public function loadSeats(): void
    {
        $this->seats = BusSeat::where('bus_id', $this->record)
            ->orderByRaw('CAST(number AS UNSIGNED)')
            ->get(['id','number','x','y','seat_type_id'])
            ->toArray();
    }

    public function loadElements(): void
    {
        $this->elements = BusLayoutElement::where('bus_id', $this->record)
            ->orderBy('type')
            ->get(['id','type','x','y','w','h','label'])
            ->toArray();
    }

    // ✨ ДОДАНО: перерахунок потрібних рядків/колонок сітки
    protected function recalcGrid(): void
    {
        $maxX = 0;
        $maxY = 0;

        foreach ($this->seats as $s) {
            $maxX = max($maxX, (int)($s['x'] ?? 0));
            $maxY = max($maxY, (int)($s['y'] ?? 0));
        }
        foreach ($this->elements as $e) {
            $maxX = max($maxX, (int)($e['x'] ?? 0) + (int)($e['w'] ?? 1) - 1);
            $maxY = max($maxY, (int)($e['y'] ?? 0) + (int)($e['h'] ?? 1) - 1);
        }

        // запас по 2 клітинки навколо + мінімальні значення
        $this->cols = max(24, $maxX + 2);
        $this->rows = max(8,  $maxY + 2);
    }

    protected function cellOccupied(int $x, int $y, ?int $exceptSeat = null, ?int $exceptEl = null): bool
    {
        $seatBusy = BusSeat::where('bus_id',$this->record)
            ->when($exceptSeat, fn($q)=>$q->where('id','!=',$exceptSeat))
            ->where('x',$x)->where('y',$y)->exists();
        if ($seatBusy) return true;

        $elBusy = BusLayoutElement::where('bus_id',$this->record)
            ->when($exceptEl, fn($q)=>$q->where('id','!=',$exceptEl))
            ->where(function($q) use ($x,$y){
                $q->whereRaw('? BETWEEN x AND (x + w - 1)', [$x])
                    ->whereRaw('? BETWEEN y AND (y + h - 1)', [$y]);
            })->exists();

        return $elBusy;
    }

    public function savePosition(int $id, int $x, int $y): void
    {
        if ($this->cellOccupied($x,$y, exceptSeat: $id)) {
            $this->dispatch('notify', type: 'danger', message: 'Клітинка зайнята елементом/місцем');
            return;
        }

        BusSeat::where('id',$id)->where('bus_id',$this->record)->update(['x'=>$x,'y'=>$y]);

        $this->loadSeats();
        $this->recalcGrid(); // ✨
        if ($bus = Bus::find($this->record)) BusLayoutSyncer::exportToSeatLayout($bus);

        $this->dispatch('notify', type: 'success', message: 'Збережено');
    }

    public function saveElementPosition(int $id, int $x, int $y): void
    {
        $el = BusLayoutElement::where('id',$id)->where('bus_id',$this->record)->firstOrFail();

        for ($dx=0; $dx<$el->w; $dx++){
            for ($dy=0; $dy<$el->h; $dy++){
                if ($this->cellOccupied($x+$dx, $y+$dy, exceptEl: $id)) {
                    $this->dispatch('notify', type: 'danger', message: 'Елемент накладається на інший об’єкт');
                    return;
                }
            }
        }

        $el->update(['x'=>$x,'y'=>$y]);

        $this->loadElements();
        $this->recalcGrid(); // ✨
        if ($bus = Bus::find($this->record)) BusLayoutSyncer::exportToSeatLayout($bus);

        $this->dispatch('notify', type: 'success', message: 'Переміщено');
    }

    // ✨ ДОДАНО: видалення сидіння
    public function deleteSeat(int $seatId): void
    {
        $seat = BusSeat::where('bus_id',$this->record)->find($seatId);
        if (! $seat) return;

        $num = $seat->number;
        $seat->delete();

        $this->loadSeats();
        $this->recalcGrid();
        if ($bus = Bus::find($this->record)) BusLayoutSyncer::exportToSeatLayout($bus);

        $this->dispatch('notify', type: 'success', message: "Видалено місце №{$num}");
    }

    // ✨ ДОДАНО: видалення елемента
    public function deleteElement(int $elementId): void
    {
        $el = BusLayoutElement::where('bus_id',$this->record)->find($elementId);
        if (! $el) return;

        $t = $el->type;
        $el->delete();

        $this->loadElements();
        $this->recalcGrid();
        if ($bus = Bus::find($this->record)) BusLayoutSyncer::exportToSeatLayout($bus);

        $this->dispatch('notify', type: 'success', message: "Елемент «{$t}» видалено");
    }

    public function addElement(string $type): void
    {
        BusLayoutElement::create([
            'bus_id'=>$this->record,'type'=>$type,'x'=>0,'y'=>0,'w'=>1,'h'=>1,
        ]);

        $this->loadElements();
        $this->recalcGrid(); // ✨
        if ($bus = Bus::find($this->record)) BusLayoutSyncer::exportToSeatLayout($bus);

        $this->dispatch('notify', type: 'success', message: 'Елемент додано');
    }

    public function setSeatType(int $seatId, ?int $seatTypeId): void
    {
        BusSeat::where('id', $seatId)
            ->where('bus_id', $this->record)
            ->update(['seat_type_id' => $seatTypeId]);

        $this->loadSeats();
        if ($bus = Bus::find($this->record)) {
            BusLayoutSyncer::exportToSeatLayout($bus);
        }
        $this->dispatch('notify', type: 'success', message: 'Тип сидіння оновлено');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addSeat')
                ->label('Додати місце')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('seat_type_id')
                        ->label('Тип сидіння')
                        ->options(SeatType::query()->pluck('name', 'id'))
                        ->searchable()
                        ->native(false),
                    Forms\Components\TextInput::make('price_modifier_abs')->label('Δ₴')->numeric()->step('0.01'),
                    Forms\Components\TextInput::make('price_modifier_pct')->label('Δ%')->numeric()->step('0.01'),
                ])
                ->action(function (array $data) {
                    $next = (int) BusSeat::where('bus_id', $this->record)
                        ->max(DB::raw('CAST(number AS UNSIGNED)'));
                    $next = $next ? $next + 1 : 1;

                    BusSeat::create([
                        'bus_id' => $this->record,
                        'number' => (string) $next,
                        'x' => 0, 'y' => 0,
                        'is_active' => true,
                        'seat_type_id' => $data['seat_type_id'] ?? null,
                        'price_modifier_abs' => $data['price_modifier_abs'] ?? null,
                        'price_modifier_pct' => $data['price_modifier_pct'] ?? null,
                    ]);

                    $this->loadSeats();
                    $this->recalcGrid(); // ✨
                    if ($bus = Bus::find($this->record)) {
                        BusLayoutSyncer::exportToSeatLayout($bus);
                    }

                    $this->dispatch('notify', type: 'success', message: 'Додано місце №'.$next);
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('addWC')->label('WC')->action(fn()=> $this->addElement('wc')),
                Actions\Action::make('addCoffee')->label('Кавомашина')->action(fn()=> $this->addElement('coffee')),
                Actions\Action::make('addDriver')->label('Водій')->action(fn()=> $this->addElement('driver')),
                Actions\Action::make('addSteward')->label('Стюардеса')->action(fn()=> $this->addElement('stuardesa')),
                Actions\Action::make('addStairs')->label('Сходи')->action(fn()=> $this->addElement('stairs')),
                Actions\Action::make('addExit')->label('Вихід')->action(fn()=> $this->addElement('exit')),
            ])->label('Додати елемент')->icon('heroicon-o-cube'),
        ];
    }
}
