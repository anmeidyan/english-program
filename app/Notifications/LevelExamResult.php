<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Certificate;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Notifications\Concerns\UsesLearnerChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** PRD 6.4: level exam result + certificate by email. */
class LevelExamResult extends Notification implements ShouldQueue
{
    use Queueable, UsesLearnerChannels;

    public function __construct(public readonly ExamAttempt $attempt, public readonly ?Certificate $certificate) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->learnerChannels($notifiable, mail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->attempt->passed ? __('Selamat! Kamu lulus :exam', ['exam' => $this->attempt->exam->title]) : __('Hasil :exam', ['exam' => $this->attempt->exam->title]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Skor total: :score', ['score' => $this->attempt->score]));

        foreach ($this->attempt->skill_scores ?? [] as $skill => $score) {
            $mail->line(ucfirst($skill).': '.$score);
        }

        if ($this->certificate !== null) {
            $mail->line(__('Kode verifikasi sertifikat: :code', ['code' => $this->certificate->code]))
                ->action(__('Lihat sertifikat'), route('certificates.verify', $this->certificate->code));
        } else {
            $mail->action(__('Lihat hasil'), route('exams.result', $this->attempt));
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->attempt->passed ? __('Level exam lulus!') : __('Hasil level exam'),
            'body' => __('Skor :score', ['score' => $this->attempt->score]),
            'url' => route('exams.result', $this->attempt),
        ];
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)->title($data['title'])->body($data['body'])->icon('/icons/icon-192.png')->data(['url' => $data['url']]);
    }
}
