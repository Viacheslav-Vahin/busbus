<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        \App\Models\Currency::upsert([
            ['code'=>'UAH','name'=>'Hryvnia','symbol'=>'₴','rate'=>1,'is_active'=>1],
            ['code'=>'PLN','name'=>'Polish Złoty','symbol'=>'zł','rate'=>0.10,'is_active'=>1],
            ['code'=>'EUR','name'=>'Euro','symbol'=>'€','rate'=>0.025,'is_active'=>1],
        ], ['code'], ['name','symbol','rate','is_active']);
    }
}
