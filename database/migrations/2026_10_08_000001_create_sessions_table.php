<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * (AUDIT FIX) The project uses SESSION_DRIVER=database but had no `sessions`
 * table migration (the default Laravel one was replaced by the custom users
 * migration, which dropped the bundled sessions table). Without this, every
 * web request 500s with "Base table or view not found: sessions".
 *
 * Standard Laravel sessions schema. Equivalent to `php artisan make:session-table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
