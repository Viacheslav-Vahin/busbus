<?php
// database/migrations/2025_09_19_000001_add_is_json_to_cms_settings.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms_settings', function (Blueprint $t) {
            $t->boolean('is_json')->default(false)->after('key');
        });
    }
    public function down(): void
    {
        Schema::table('cms_settings', function (Blueprint $t) {
            $t->dropColumn('is_json');
        });
    }
};
