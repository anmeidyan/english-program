<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Recordings live outside public; served only through signed URLs to the owner or a reviewer (PRD 13.1). */
class RecordingController extends Controller
{
    public function __invoke(Request $request, Submission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless($submission->user_id === $user->id || $user->can('submissions.review'), 403);
        abort_if($submission->file_path === null, 404);

        $disk = Storage::disk(config('englishpath.media.recordings_disk', 'local'));
        abort_unless($disk->exists($submission->file_path), 404);

        return $disk->response($submission->file_path, null, ['Accept-Ranges' => 'bytes']);
    }
}
