<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Question;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\TenseTopic;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserFlashcard;
use App\Models\Vocabulary;
use App\Services\Review\AiGraderInterface;
use App\Services\Review\AnthropicAiGrader;
use App\Services\Review\NullAiGrader;
use App\Services\Speech\BrowserSpeechToText;
use App\Services\Speech\SpeechToTextInterface;
use App\Services\Speech\WhisperSpeechToText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeechToTextInterface::class, fn () => config('englishpath.speech.driver') === 'whisper'
            ? new WhisperSpeechToText
            : new BrowserSpeechToText);

        $this->app->bind(AiGraderInterface::class, fn () => match (config('englishpath.ai_grader.driver')) {
            'anthropic' => new AnthropicAiGrader,
            default => new NullAiGrader,
        });

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'lesson' => Lesson::class,
            'unit' => Unit::class,
            'level' => Level::class,
            'tense_topic' => TenseTopic::class,
            'user' => User::class,
            'module' => Module::class,
            'exam' => Exam::class,
            'exam_attempt' => ExamAttempt::class,
            'question' => Question::class,
            'submission' => Submission::class,
            'vocabulary' => Vocabulary::class,
            'certificate' => Certificate::class,
            'user_flashcard' => UserFlashcard::class,
            'setting' => Setting::class,
        ]);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
