<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled reminders. The cron-driven command scans for due, pending rows.
 *
 * type:   same_day | confirmation | custom
 * status: pending | sent | failed | cancelled
 * channel: free_form | template  (decided at send time from the 24h window)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('same_day');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('channel')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);   // the cron scan query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reminders');
    }
};
