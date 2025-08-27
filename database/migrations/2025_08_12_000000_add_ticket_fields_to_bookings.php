<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function fkExists(string $table, string $fkName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->exists();
    }

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            if (!Schema::hasColumn('bookings', 'ticket_uuid')) {
                $t->uuid('ticket_uuid')->nullable()->unique();
            }
            if (!Schema::hasColumn('bookings', 'qr_path')) {
                $t->string('qr_path')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'ticket_pdf_path')) {
                $t->string('ticket_pdf_path')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'checked_in_at')) {
                $t->timestamp('checked_in_at')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'checked_in_by')) {
                $t->unsignedBigInteger('checked_in_by')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'checkin_place')) {
                $t->string('checkin_place')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'ticket_serial')) {
                $t->string('ticket_serial')->nullable()->index();
            }
        });

        if (!$this->fkExists('bookings', 'bookings_checked_in_by_foreign')) {
            Schema::table('bookings', function (Blueprint $t) {
                $t->foreign('checked_in_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->fkExists('bookings', 'bookings_checked_in_by_foreign')) {
            Schema::table('bookings', function (Blueprint $t) {
                $t->dropForeign('bookings_checked_in_by_foreign');
            });
        }

        Schema::table('bookings', function (Blueprint $t) {
            foreach ([
                         'ticket_uuid','qr_path','ticket_pdf_path',
                         'checked_in_at','checked_in_by','checkin_place','ticket_serial',
                     ] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
