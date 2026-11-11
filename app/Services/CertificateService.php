<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\Models\ExamAttempt;
use App\Models\Level;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** PRD 5.6: internal PDF certificate with a public verification code. */
class CertificateService
{
    public const DISK = 'local';

    public function issue(ExamAttempt $attempt): ?Certificate
    {
        $level = $attempt->exam->examable;
        if (! $level instanceof Level || ! $attempt->passed) {
            return null;
        }

        $certificate = Certificate::query()->firstOrCreate(
            ['user_id' => $attempt->user_id, 'level_id' => $level->id],
            [
                'exam_attempt_id' => $attempt->id,
                'code' => $this->uniqueCode(),
                'skill_scores' => $attempt->skill_scores ?? [],
                'score' => $attempt->score ?? 0,
                'issued_at' => now(),
            ],
        );

        if ($certificate->pdf_path === null) {
            $this->renderPdf($certificate);
        }

        return $certificate;
    }

    public function renderPdf(Certificate $certificate): string
    {
        $certificate->loadMissing('user', 'level');
        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'verifyUrl' => route('certificates.verify', $certificate->code),
        ])->setPaper('a4', 'landscape');

        $path = "certificates/{$certificate->code}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output());
        $certificate->update(['pdf_path' => $path]);

        return $path;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'EP-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (Certificate::query()->where('code', $code)->exists());

        return $code;
    }
}
