<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tech Provider / Coexistence foundation: one row per connected business number.
 * Each onboarded number (incl. coexistence numbers from Embedded Signup) brings
 * its own phone_number_id + access token, so one app can serve many restaurants.
 *
 * contacts.wa_phone_number_id records WHICH business number a guest talks to, so
 * the bot replies from that same number. With no rows here the client falls back
 * to config('services.whatsapp.*') — the current single/test number keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number_id')->unique();
            $table->string('waba_id')->nullable();
            $table->text('token');                             // business / system-user token for this number
            $table->string('label')->nullable();               // e.g. "Humareda Prime (coexistencia)"
            $table->boolean('is_coexistence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('wa_phone_number_id')->nullable()->after('wa_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('wa_phone_number_id');
        });
        Schema::dropIfExists('whatsapp_numbers');
    }
};
