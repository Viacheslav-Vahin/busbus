<?php
// database/migrations/2025_08_11_000000_create_company_profiles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('company_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('edrpou')->nullable();
            $t->string('iban')->nullable();
            $t->string('bank')->nullable();
            $t->string('addr')->nullable();
            $t->string('vat')->nullable(); // «Платник ПДВ…» або «неплатник ПДВ»
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('company_profiles');
    }
};

