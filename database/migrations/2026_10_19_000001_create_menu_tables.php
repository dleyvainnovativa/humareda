<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú (T13): el menú del restaurante como datos estructurados, no como FAQ.
 * ext_id guarda el id del JSON de origen para poder reimportar (upsert) cuando
 * el cliente mande un menú nuevo. price_type: fixed | per_100_g | not_listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ext_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->json('extras')->nullable();              // [{name, price, portion}]
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ext_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('portion_qty', 8, 2)->nullable();
            $table->string('portion_unit', 16)->nullable();
            $table->decimal('price', 10, 2)->nullable();      // null = no listado
            $table->string('price_type', 20)->default('fixed');
            $table->json('options')->nullable();
            $table->json('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
