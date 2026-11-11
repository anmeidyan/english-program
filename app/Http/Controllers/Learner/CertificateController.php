<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function index(Request $request): View
    {
        return view('learner.certificates', [
            'certificates' => $request->user()->certificates()->with('level')->latest('issued_at')->get(),
        ]);
    }

    public function download(Request $request, Certificate $certificate): StreamedResponse
    {
        abort_unless($certificate->user_id === $request->user()->id || $request->user()->isStaff(), 403);
        $path = $certificate->pdf_path ?? $this->certificates->renderPdf($certificate);
        if (! Storage::disk(CertificateService::DISK)->exists($path)) {
            $path = $this->certificates->renderPdf($certificate);
        }

        return Storage::disk(CertificateService::DISK)->download($path, "EnglishPath-{$certificate->code}.pdf");
    }

    /** Public verification page /verify/{code} (PRD 5.6). */
    public function verify(string $code): View
    {
        $certificate = Certificate::query()->with('user', 'level')->where('code', strtoupper($code))->first();

        return view('certificates.verify', ['certificate' => $certificate, 'code' => $code]);
    }
}
