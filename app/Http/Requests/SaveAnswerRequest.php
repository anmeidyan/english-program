<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answer' => ['present', 'nullable', function (string $attr, mixed $value, \Closure $fail) {
                if (strlen((string) json_encode($value)) > 20000) {
                    $fail(__('Jawaban terlalu panjang.'));
                }
            }],
        ];
    }
}
