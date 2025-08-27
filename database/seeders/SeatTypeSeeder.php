<?php
// database/seeders/SeatTypeSeeder.php
namespace Database\Seeders;

use App\Models\SeatType;
use Illuminate\Database\Seeder;

class SeatTypeSeeder extends Seeder {
    public function run(): void {
        SeatType::upsert([
            ['code'=>'classic','name'=>'Класичне','modifier_type'=>'percent','modifier_value'=>0],
            ['code'=>'recliner','name'=>'Реклайнер','modifier_type'=>'percent','modifier_value'=>10],
            ['code'=>'panoramic','name'=>'Панорамне','modifier_type'=>'absolute','modifier_value'=>150],
        ], ['code'], ['name','modifier_type','modifier_value']);
    }
}
