<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lesson;
use App\Services\Grading\QuestionPresenter;
use Illuminate\Support\Facades\Storage;

/** PRD 11.3: JSON bundle + media list of a lesson for Cache Storage / IndexedDB. */
class OfflineBundleService
{
    public function __construct(private readonly QuestionPresenter $presenter) {}

    /** @return array<string, mixed> */
    public function bundle(Lesson $lesson): array
    {
        $lesson->loadMissing('questions', 'vocabularies', 'unit');
        $media = [];
        $disk = Storage::disk(config('englishpath.media.disk'));

        foreach ($lesson->content ?? [] as $block) {
            $url = $block['data']['url'] ?? null;
            if (in_array($block['type'], ['audio', 'image'], true) && $url) {
                $media[] = str_starts_with($url, 'http') ? $url : $disk->url($url);
            }
        }

        return [
            'version' => $lesson->updated_at?->timestamp,
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'type' => $lesson->type->value,
                'unit' => $lesson->unit->title,
                'content' => $lesson->content ?? [],
            ],
            'questions' => $lesson->questions->map(fn ($q) => $this->presenter->present($q, QuestionPresenter::MODE_PRACTICE))->values(),
            'vocabulary' => $lesson->vocabularies->map->only(['id', 'word', 'part_of_speech', 'ipa_us', 'ipa_uk', 'meaning_id', 'definition_en', 'example'])->values(),
            'page_url' => route('lessons.show', $lesson),
            'media' => array_values(array_unique($media)),
        ];
    }
}
