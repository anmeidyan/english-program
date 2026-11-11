<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('examable_type');
            $table->unsignedBigInteger('examable_id');
            $table->string('type', 30);
            $table->string('title');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('passing_score');
            $table->unsignedTinyInteger('min_skill_score')->default(0);
            $table->unsignedSmallInteger('question_count');
            $table->json('cooldown_rules')->nullable();
            $table->unsignedTinyInteger('max_audio_plays')->default(2);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['examable_type', 'examable_id']);
        });

        Schema::create('exam_question_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('skill', 20)->nullable();
            $table->string('question_type', 30)->nullable();
            $table->foreignId('tag_id')->nullable()->constrained('question_tags')->nullOnDelete();
            $table->unsignedTinyInteger('difficulty_min')->default(1);
            $table->unsignedTinyInteger('difficulty_max')->default(5);
            $table->unsignedSmallInteger('count');
            $table->timestamps();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 20);
            // "user:exam" while the attempt is active, NULL otherwise → one active attempt per user per exam.
            $table->string('active_key')->nullable()->unique();
            $table->json('question_ids');
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->json('skill_scores')->nullable();
            $table->boolean('passed')->nullable();
            $table->json('integrity_log')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'exam_id', 'status']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 6, 2)->default(0);
            $table->unsignedTinyInteger('audio_plays')->default(0);
            $table->string('graded_by', 20)->nullable();
            $table->timestamps();
            $table->unique(['exam_attempt_id', 'question_id']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('file_path')->nullable();
            $table->longText('text')->nullable();
            $table->text('transcript')->nullable();
            $table->json('ai_score')->nullable();
            $table->json('final_score')->nullable();
            $table->text('feedback')->nullable();
            $table->json('inline_feedback')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_question_rules');
        Schema::dropIfExists('exams');
    }
};
