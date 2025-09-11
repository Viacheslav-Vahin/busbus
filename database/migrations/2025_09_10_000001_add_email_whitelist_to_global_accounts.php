<?php
// database/migrations/2025_09_10_000001_add_email_whitelist_to_global_accounts.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('global_accounts', function (Blueprint $table) {
            $table->text('email_whitelist')->nullable()->after('details'); // комою розділені e-mail'и
        });
    }

    public function down(): void
    {
        Schema::table('global_accounts', function (Blueprint $table) {
            $table->dropColumn('email_whitelist');
        });
    }
};
