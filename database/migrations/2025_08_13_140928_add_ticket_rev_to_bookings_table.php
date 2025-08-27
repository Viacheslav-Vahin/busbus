<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_ticket_rev_to_bookings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('ticket_rev', 20)->nullable()->after('ticket_uuid');
            $t->index(['ticket_uuid', 'ticket_rev'], 'bookings_ticket_uuid_rev_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex('bookings_ticket_uuid_rev_idx');
            $t->dropColumn('ticket_rev');
        });
    }
};

