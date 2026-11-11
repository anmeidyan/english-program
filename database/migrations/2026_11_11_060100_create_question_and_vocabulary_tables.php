<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('skill', 20);
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->text('prompt');
            $table->json('payload')->nullable();
            $table->json('answer_key')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->string('media_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'skill', 'level_id']);
        });

        Schema::create('question_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('question_tags')->cascadeOnDelete();
            $table->primary(['question_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('lesson_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['lesson_id', 'question_id']);
        });

        Schema::create('vocabularies', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->string('part_of_speech', 30)->nullable();
            $table->string('ipa_us')->nullable();
            $table->string('ipa_uk')->nullable();
            $table->string('audio_us')->nullable();
            $table->string('audio_uk')->nullable();
            $table->string('meaning_id');
            $table->text('definition_en')->nullable();
            $table->text('example')->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('word');
        });

        Schema::create('lesson_vocabulary', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_id', 'vocabulary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_vocabulary');
        Schema::dropIfExists('vocabularies');
        Schema::dropIfExists('lesson_question');
        Schema::dropIfExists('question_tag');
        Schema::dropIfExists('question_tags');
        Schema::dropIfExists('questions');
    }
};
