<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Exam;
use App\Models\User;
use App\Notifications\Concerns\UsesLearnerChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class CooldownEnded extends Notification implements ShouldQueue
{
    use Queueable, UsesLearnerChannels;

    public function __construct(public readonly Exam $exam) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->learnerChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Masa tunggu selesai'),
            'body' => __('Kamu bisa mencoba lagi :exam.', ['exam' => $this->exam->title]),
            'url' => route('exams.show', $this->exam),
        ];
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)->title($data['title'])->body($data['body'])->icon('/icons/icon-192.png')->data(['url' => $data['url']]);
    }
}
