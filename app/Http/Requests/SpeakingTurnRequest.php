<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpeakingTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer'],
            'transcript' => ['required', 'string', 'min:2', 'max:'.(int) config('englishpath.speaking.max_chars', 1000)],
        ];
    }
}
