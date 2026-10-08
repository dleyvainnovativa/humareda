<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-weekday capacity configuration. Drives the T3 availability engine.
 *
 * day_of_week: 0=Sunday ... 6=Saturday (matches Carbon::dayOfWeek)
 *
 * NOTE: capacity numbers below are PLACEHOLDERS seeded from the site's
 * published hours. max_covers_per_slot / turn_minutes / auto_confirm_max
 * MUST be confirmed with the client before T3 goes live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week');       // 0..6
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('last_seating')->nullable();         // latest a booking may start
            $table->time('close_time')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->unsignedSmallInteger('turn_minutes')->default(120); // dinner turn length
            $table->unsignedSmallInteger('max_covers_per_slot')->default(40);
            $table->unsignedSmallInteger('auto_confirm_max')->default(8); // above -> human
            $table->timestamps();

            $table->unique('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_hours');
    }
};
