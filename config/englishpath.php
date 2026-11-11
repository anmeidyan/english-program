<?php

return [
    // Personal install: list the demo accounts on the login page with one-click sign-in.
    'demo_logins' => [
        'enabled' => (bool) env('SHOW_DEMO_LOGINS', true),
        'password' => 'password',
        'accounts' => [
            ['email' => 'student@demo.test', 'role' => 'Pelajar'],
            ['email' => 'admin@demo.test', 'role' => 'Super admin'],
            ['email' => 'content@demo.test', 'role' => 'Content admin'],
            ['email' => 'reviewer@demo.test', 'role' => 'Reviewer'],
        ],
    ],

    'speech' => [
        // browser = Web Speech API in the learner's browser; whisper = server-side transcription via queue
        'driver' => env('SPEECH_DRIVER', 'browser'),
        'whisper_api_key' => env('WHISPER_API_KEY'),
    ],

    'ai_grader' => [
        // null | openai | anthropic
        'driver' => env('AI_GRADER_DRIVER', 'null'),
        'api_key' => env('AI_GRADER_API_KEY'),
        'model' => env('AI_GRADER_MODEL'),
    ],

    'media' => [
        'disk' => env('FILESYSTEM_DISK', 'public'),
        'recordings_disk' => env('RECORDINGS_DISK', 'local'),
        'max_audio_kb' => 10 * 1024,
        'max_image_kb' => 2 * 1024,
        'max_recording_seconds' => 120,
    ],

    'flashcards' => [
        'max_due' => 50,
        'max_new' => 10,
    ],

    'xp' => [
        'lesson_completed' => 10,
        'quiz_passed' => 20,
        'unit_exam_passed' => 100,
        'level_exam_passed' => 500,
        'tenses_checkpoint_passed' => 20,
        'flashcards_daily' => 5,
    ],

    'streak' => [
        'flashcards_per_day' => 20,
    ],
];
