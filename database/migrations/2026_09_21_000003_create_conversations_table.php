<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One live conversation per contact. Holds the state-machine state and
 * the in-progress booking context (partial slots + short-term memory).
 *
 * state:
 *   idle | booking | confirming | cancelling | modifying | human
 *
 * context (JSON): partial reservation being built, last turns, etc.
 * last_inbound_at: drives the WhatsApp 24h free-window logic for reminders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default('idle');
            $table->json('context')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->timestamps();

            $table->unique('contact_id');   // one active conversation per contact
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
