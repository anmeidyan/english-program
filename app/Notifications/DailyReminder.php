<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\UsesLearnerChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class DailyReminder extends Notification implements ShouldQueue
{
    use Queueable, UsesLearnerChannels;

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->learnerChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Waktunya belajar!'),
            'body' => $notifiable->streak_current > 0
                ? __('Jaga streak :days harimu — :minutes menit hari ini.', ['days' => $notifiable->streak_current, 'minutes' => $notifiable->daily_goal_minutes])
                : __('Target harianmu :minutes menit. Yuk mulai!', ['minutes' => $notifiable->daily_goal_minutes]),
            'url' => route('dashboard'),
        ];
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)->title($data['title'])->body($data['body'])->icon('/icons/icon-192.png')->data(['url' => $data['url']]);
    }
}
