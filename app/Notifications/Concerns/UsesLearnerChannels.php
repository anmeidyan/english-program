<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;

trait UsesLearnerChannels
{
    /** In-app always; Web Push when the learner subscribed and VAPID keys exist. */
    /** @return list<string> */
    protected function learnerChannels(User $user, bool $mail = false): array
    {
        $channels = ['database'];
        if ($mail) {
            $channels[] = 'mail';
        }
        if (config('webpush.vapid.public_key') && $user->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }
}
