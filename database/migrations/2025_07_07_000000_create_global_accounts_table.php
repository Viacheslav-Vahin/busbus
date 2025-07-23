<?php
// database/migrations/2025_07_07_000000_create_global_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('global_accounts', function (Blueprint $table) {
$table->id();
$table->string('title')->default('Рахунок'); // Наприклад: "Рахунок 1", "Рахунок 2"
$table->text('details'); // Сюди можна вставити довільний текст, наприклад реквізити
$table->timestamps();
});
}

public function down(): void
{
Schema::dropIfExists('global_accounts');
}
};
