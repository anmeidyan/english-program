<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('id')->after('password');
            $table->string('timezone', 64)->default('Asia/Jakarta')->after('locale');
            $table->unsignedSmallInteger('daily_goal_minutes')->default(20)->after('timezone');
            $table->time('reminder_time')->nullable()->after('daily_goal_minutes');
            $table->string('learning_goal')->nullable()->after('reminder_time');
            $table->unsignedBigInteger('current_level_id')->nullable()->after('learning_goal');
            $table->unsignedInteger('xp_total')->default(0)->after('current_level_id');
            $table->unsignedInteger('streak_current')->default(0)->after('xp_total');
            $table->unsignedInteger('streak_longest')->default(0)->after('streak_current');
            $table->date('last_active_date')->nullable()->after('streak_longest');
            $table->date('streak_freeze_used_at')->nullable()->after('last_active_date');
            $table->timestamp('onboarded_at')->nullable()->after('streak_freeze_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'locale', 'timezone', 'daily_goal_minutes', 'reminder_time', 'learning_goal', 'current_level_id',
                'xp_total', 'streak_current', 'streak_longest', 'last_active_date', 'streak_freeze_used_at', 'onboarded_at',
            ]);
        });
    }
};
