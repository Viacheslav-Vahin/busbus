<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->json('operation_days')->nullable();
            $table->json('off_days')->nullable();
            $table->boolean('has_operation_days')->default(false);
            $table->boolean('has_off_days')->default(false);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn(['operation_days', 'off_days', 'has_operation_days', 'has_off_days']);
        });
    }

};
