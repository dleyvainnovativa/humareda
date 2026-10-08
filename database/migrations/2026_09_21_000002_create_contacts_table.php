<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A WhatsApp guest. Keyed by their wa_id (phone in WhatsApp format).
 * One row per person who ever messages the bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->unique();               // e.g. 522295507070
            $table->string('name')->nullable();              // profile name or captured name
            $table->string('locale', 5)->default('es');      // es | en (last detected)
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
