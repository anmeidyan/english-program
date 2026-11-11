<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProgressStatus;
use App\Exceptions\ExamException;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Grading\GraderRegistry;

/**
 * Lesson practice (PRD 4): unlimited retries, instant feedback with the correct answer
 * and explanation. Practice questions are not exam material, so revealing them is fine.
 */
class PracticeService
{
    public function __construct(
        private readonly GraderRegistry $graders,
        private readonly ProgressRepositoryInterface $progress,
    ) {}

    /** @return array<string, mixed> */
    public function check(Lesson $lesson, Question $question, mixed $answer): array
    {
        if (! $lesson->questions()->whereKey($question->id)->exists()) {
            throw new ExamException(__('Soal tidak termasuk dalam lesson ini.'), 404);
        }

        $grade = $this->graders->grade($question, $answer);
        $payload = $question->payload ?? [];

        return [
            'question_id' => $question->id,
            'is_correct' => $grade->isCorrect,
            'fraction' => $grade->fraction,
            'details' => $grade->details,
            'correct_answer' => $this->graders->correctAnswer($question),
            'explanation' => $question->explanation,
            'transcript' => $payload['transcript'] ?? null,
        ];
    }

    /**
     * Grades a batch (also used to sync answers made offline) and keeps the best score.
     *
     * @param  list<array{question_id:int, answer:mixed}>  $items
     * @return array{results: list<array<string, mixed>>, score: float, best: float}
     */
    public function checkBatch(User $user, Lesson $lesson, array $items): array
    {
        $ids = array_map(fn ($i) => (int) $i['question_id'], $items);
        $questions = $lesson->questions()->whereIn('questions.id', $ids)->get()->keyBy('id');

        $results = [];
        $sum = 0.0;
        foreach ($items as $item) {
            $question = $questions->get((int) $item['question_id']);
            if ($question === null) {
                continue;
            }
            $result = $this->check($lesson, $question, $item['answer'] ?? null);
            $results[] = $result;
            $sum += (float) $result['fraction'];
        }

        $total = max(1, $lesson->questions()->count());
        $score = round($sum / $total * 100, 2);
        $row = $this->progress->find($user, $lesson);
        $best = max((float) ($row->practice_best_score ?? 0), $score);
        // best score wins (PRD 11.3) — status never changes here
        $this->progress->put($user, $lesson, $row->status ?? ProgressStatus::Unlocked, ['practice_best_score' => $best]);

        return ['results' => $results, 'score' => $score, 'best' => $best];
    }
}
