<?php

declare(strict_types=1);

use App\Enums\ProgressStatus;
use App\Models\Level;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\PlacementService;
use Database\Seeders\LevelSeeder;

beforeEach(function () {
    $this->seed(LevelSeeder::class);
    Level::query()->whereIn('code', ['A1', 'A2', 'B1'])->update(['is_published' => true]);
    $this->placement = app(PlacementService::class);
});

it('moves up after a passed stage and stops at the first failed level', function () {
    expect($this->placement->advance('A1', 5, ['A1' => 5]))->toBe(['done' => false, 'next' => 'A2', 'recommended' => null])
        ->and($this->placement->advance('A2', 2, ['A1' => 5, 'A2' => 2]))->toBe(['done' => true, 'next' => null, 'recommended' => 'A2']);
});

it('steps down to Pre-A1 when A1 is failed', function () {
    expect($this->placement->advance('A1', 1, ['A1' => 1]))->toBe(['done' => false, 'next' => 'PRE_A1', 'recommended' => null])
        ->and($this->placement->advance('PRE_A1', 5, ['A1' => 1, 'PRE_A1' => 5]))->toBe(['done' => true, 'next' => null, 'recommended' => 'A1']);
});

it('marks lower levels passed_by_placement and allows one retake per 30 days', function () {
    $user = student();
    $result = $this->placement->finish($user, 'B1', ['A1' => 5, 'A2' => 4, 'B1' => 1]);
    $progress = app(ProgressRepositoryInterface::class);

    expect($result->recommendedLevel->code->value)->toBe('B1')
        ->and($progress->status($user, Level::where('code', 'A1')->first()))->toBe(ProgressStatus::PassedByPlacement)
        ->and($progress->status($user, Level::where('code', 'A2')->first()))->toBe(ProgressStatus::PassedByPlacement)
        ->and($user->fresh()->current_level_id)->toBe(Level::where('code', 'B1')->value('id'))
        ->and($this->placement->nextAllowedAt($user))->not->toBeNull();

    $this->travel(31)->days();
    expect($this->placement->nextAllowedAt($user))->toBeNull();
});

it('clamps a recommendation to the highest published level', function () {
    expect($this->placement->resolvePublished('C2')->code->value)->toBe('B1')
        ->and($this->placement->resolvePublished('PRE_A1')->code->value)->toBe('A1');
});
