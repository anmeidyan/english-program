<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('practice_date');
            $table->string('topic_key', 60);
            $table->string('level_code', 10);
            $table->unsignedSmallInteger('turns_count')->default(0);
            $table->decimal('avg_score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'practice_date', 'topic_key']);
        });

        Schema::create('speaking_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('speaking_session_id')->constrained()->cascadeOnDelete();
            $table->text('transcript');
            $table->text('corrected');
            $table->unsignedTinyInteger('score');
            $table->json('corrections');
            $table->text('tip')->nullable();
            $table->text('reply');
            $table->string('coach', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_turns');
        Schema::dropIfExists('speaking_sessions');
    }
};
