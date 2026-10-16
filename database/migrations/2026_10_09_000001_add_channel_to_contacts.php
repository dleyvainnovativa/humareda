<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multichannel (T12): a contact is now identified by (channel, external_id),
 * not just a WhatsApp wa_id. external_id is the channel-scoped user id:
 *   whatsapp  -> the wa_id (52 + 1 + 10)
 *   messenger -> the PSID
 *   instagram -> the IGSID
 * Existing rows are WhatsApp; external_id is backfilled from wa_id and wa_id is
 * kept populated for them so legacy WhatsApp code paths keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('channel', 20)->default('whatsapp')->after('id');
            $table->string('external_id')->nullable()->after('channel');
        });

        // Backfill: existing contacts are WhatsApp; their external_id is the wa_id.
        DB::table('contacts')->whereNull('external_id')->update([
            'external_id' => DB::raw('wa_id'),
        ]);

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('wa_id')->nullable()->change(); // future channels have no wa_id
            $table->unique(['channel', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['channel', 'external_id']);
            $table->dropColumn(['channel', 'external_id']);
        });
    }
};
