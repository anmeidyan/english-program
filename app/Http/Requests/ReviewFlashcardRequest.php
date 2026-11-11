<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FlashcardRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewFlashcardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('card')?->user_id === $this->user()?->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['rating' => ['required', Rule::enum(FlashcardRating::class)]];
    }
}
