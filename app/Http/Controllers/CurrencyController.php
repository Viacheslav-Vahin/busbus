<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Support\Facades\Schema;

class CurrencyController extends Controller
{
    public function index()
    {
        // Якщо таблиці немає — одразу фолбек, щоб не ловити 1146 "Base table not found"
        if (! Schema::hasTable('currencies')) {
            return response()->json($this->fallback());
        }

        $q = Currency::query()->select('code', 'rate', 'symbol');

        // Фільтр за активністю — лише якщо є така колонка
        if (Schema::hasColumn('currencies', 'is_active')) {
            $q->where('is_active', 1);
        }

        // Сортування: спершу 'sort' якщо є, інакше — по 'code'
        if (Schema::hasColumn('currencies', 'sort')) {
            $q->orderBy('sort');
        } else {
            $q->orderBy('code');
        }

        $items = $q->get()->map(fn ($c) => [
            'code'   => (string) $c->code,
            // rate — множник UAH -> currency
            'rate'   => (float) ($c->rate ?: 1),
            'symbol' => (string) $c->symbol,
        ]);

        // Якщо з таблиці нічого не прийшло — віддамо фолбек
        if ($items->isEmpty()) {
            return response()->json($this->fallback());
        }

        return response()->json($items->values());
    }

    private function fallback(): array
    {
        return [
            ['code' => 'UAH', 'rate' => 1.0,   'symbol' => '₴'],
            ['code' => 'EUR', 'rate' => 0.025, 'symbol' => '€'],  // твій курс UAH→EUR
            ['code' => 'PLN', 'rate' => 0.11,  'symbol' => 'zł'], // твій курс UAH→PLN
        ];
    }
}
