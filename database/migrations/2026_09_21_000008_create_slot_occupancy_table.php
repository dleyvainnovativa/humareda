<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover accounting per (date, slot_start). One row per occupied slot.
 * A booking that spans N slots writes/updates N rows.
 *
 * This is the table the T3 availability engine locks (SELECT ... FOR UPDATE)
 * to make check-and-write atomic and prevent overselling a slot under
 * concurrent bookings. Kept separate from `reservations` precisely so it
 * can be locked cheaply without contending on the reservation rows.
 *
 * The unique (reserved_date, slot_start) lets us upsert covers safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_occupancy', function (Blueprint $table) {
            $table->id();
            $table->date('reserved_date');
            $table->time('slot_start');
            $table->unsignedSmallInteger('covers')->default(0);
            $table->timestamps();

            $table->unique(['reserved_date', 'slot_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_occupancy');
    }
};
