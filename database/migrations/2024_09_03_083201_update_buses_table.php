<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateBusesTable extends Migration
{
    public function up()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->renameColumn('seats', 'seats_count');
            $table->renameColumn('license_plate', 'registration_number');
        });
    }

    public function down()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->renameColumn('seats_count', 'seats');
            $table->renameColumn('registration_number', 'license_plate');
        });
    }
}
