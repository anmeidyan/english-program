<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Single answer {question_id, answer} or an offline batch {items: [...]}. */
class PracticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_id' => ['required_without:items', 'integer'],
            'answer' => ['nullable'],
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*.question_id' => ['required', 'integer'],
            'items.*.answer' => ['nullable'],
        ];
    }
}
