<?php
// database/migrations/2025_09_18_000001_create_cms_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cms_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();           // 'home', 'about', 'contacts'
            $t->json('title');                      // {"uk":"...", "en":"..."}
            $t->json('blocks')->nullable();         // масив блоків
            $t->json('meta')->nullable();           // seo, cover, etc
            $t->enum('status', ['draft','published'])->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('cms_menus', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();            // 'header', 'footer'
            $t->json('items');                      // [{title:{uk:""}, url:"#", children:[...]}]
            $t->timestamps();
        });

        Schema::create('cms_settings', function (Blueprint $t) {
            $t->id();
            $t->string('group')->default('site');   // 'site','contacts','payments'
            $t->string('key')->unique();            // 'phone','email','logo_url'
            $t->json('value')->nullable();          // {"uk":"...", "...": "..."} або простий JSON
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_pages');
    }
};
