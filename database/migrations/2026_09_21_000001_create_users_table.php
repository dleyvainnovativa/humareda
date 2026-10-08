<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local staff/admin users. Identity is owned by Firebase Auth;
 * this table mirrors the account so we can attach roles, sessions,
 * and audit trails in MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid')->unique();       // Firebase Auth UID
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('staff');        // admin | staff
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
