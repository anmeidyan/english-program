<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamAttempt;
use App\Models\User;
use App\Notifications\Concerns\UsesLearnerChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class ReviewResultAvailable extends Notification implements ShouldQueue
{
    use Queueable, UsesLearnerChannels;

    public function __construct(public readonly ExamAttempt $attempt) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->learnerChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Hasil penilaian tersedia'),
            'body' => __(':exam — skor :score (:status)', [
                'exam' => $this->attempt->exam->title,
                'score' => $this->attempt->score,
                'status' => $this->attempt->passed ? __('lulus') : __('belum lulus'),
            ]),
            'url' => route('exams.result', $this->attempt),
        ];
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)->title($data['title'])->body($data['body'])->icon('/icons/icon-192.png')->data(['url' => $data['url']]);
    }
}
