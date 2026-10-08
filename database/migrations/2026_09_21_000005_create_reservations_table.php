<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reservation. `reserved_date` + `reserved_time` are the seating start.
 * Cover accounting (which slots this booking occupies) lives in
 * slot_occupancy, written atomically alongside a confirmed reservation.
 *
 * status: pending | confirmed | cancelled | completed | no_show
 * source: bot | staff | web
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->date('reserved_date');
            $table->time('reserved_time');
            $table->unsignedSmallInteger('party_size');
            $table->string('name');                          // name the table is under
            $table->string('status')->default('confirmed');
            $table->string('source')->default('bot');
            $table->text('notes')->nullable();               // special requests captured free-text
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['reserved_date', 'reserved_time']);
            $table->index(['contact_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
