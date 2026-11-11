<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** MIME sniffing + size limit (PRD 13.1); webm/ogg/mp4 accepted (PRD 11.4). */
class UploadRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/aac,audio/mpeg'],
            'transcript' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
