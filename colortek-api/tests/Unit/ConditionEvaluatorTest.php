<?php

declare(strict_types=1);

use App\Models\Task;
use App\Services\Workflow\ConditionEvaluator;

it('treats an unresolvable field as empty instead of throwing', function (): void {
    $task = Task::factory()->create();

    $result = app(ConditionEvaluator::class)->passes(
        ['field' => 'nonexistent', 'operator' => 'is_empty', 'value' => null],
        $task,
    );

    expect($result)->toBeTrue();
});

it('evaluates equals against task field values', function (): void {
    $task = Task::factory()->create();
    $task->fieldValues()->create(['key' => 'decision', 'value' => 'approved']);

    $result = app(ConditionEvaluator::class)->passes(
        ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'],
        $task,
    );

    expect($result)->toBeTrue();
});

it('evaluates all combinator', function (): void {
    $task = Task::factory()->create();
    $task->fieldValues()->create(['key' => 'decision', 'value' => 'rejected']);

    $result = app(ConditionEvaluator::class)->passes([
        'all' => [
            ['field' => 'decision', 'operator' => 'equals', 'value' => 'rejected'],
            ['field' => 'missing', 'operator' => 'is_empty', 'value' => null],
        ],
    ], $task);

    expect($result)->toBeTrue();
});

it('evaluates any combinator', function (): void {
    $task = Task::factory()->create();

    $result = app(ConditionEvaluator::class)->passes([
        'any' => [
            ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'],
            ['field' => 'decision', 'operator' => 'equals', 'value' => 'rejected'],
        ],
    ], $task);

    expect($result)->toBeFalse();
});

it('evaluates none combinator', function (): void {
    $task = Task::factory()->create();

    $result = app(ConditionEvaluator::class)->passes([
        'none' => [
            ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'],
        ],
    ], $task);

    expect($result)->toBeTrue();
});
