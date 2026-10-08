<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full audit log of every inbound and outbound message. Invaluable for
 * debugging and for staff to review threads in the panel.
 *
 * wa_message_id is unique -> primary dedupe guard against Meta webhook retries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3);                  // in | out
            $table->string('type')->default('text');         // text | audio | image | sticker | template | system
            $table->text('body')->nullable();                // text or transcript
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('sender')->nullable();            // bot | staff email | guest
            $table->json('payload')->nullable();             // raw webhook / send payload
            $table->timestamp('created_at')->nullable();

            $table->index(['contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
