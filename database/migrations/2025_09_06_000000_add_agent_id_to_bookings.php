<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->after('status')->index();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
