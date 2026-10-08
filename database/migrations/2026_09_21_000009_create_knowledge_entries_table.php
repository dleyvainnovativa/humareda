<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable knowledge base for the FAQ branch. Staff edit these in the panel;
 * the bot answers menu/hours/dietary questions ONLY from active entries and
 * never invents. Bilingual columns so the bot can answer in the guest's language.
 *
 * category: menu | hours | location | dietary | policy | general
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('general');
            $table->string('question_es');
            $table->text('answer_es');
            $table->string('question_en')->nullable();
            $table->text('answer_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
