<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11: reservations are now captured as requests and authorized by a human.
 * Adds the reference contact the client asked for, and review metadata.
 * The 'rejected' status is just a new value of the existing string column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference_contact')->nullable()->after('name'); // email or phone
            $table->timestamp('reviewed_at')->nullable()->after('status');
            $table->string('reviewed_by')->nullable()->after('reviewed_at'); // staff email
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['reference_contact', 'reviewed_at', 'reviewed_by']);
        });
    }
};
