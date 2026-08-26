<?php

declare(strict_types=1);

use App\Enums\TaskStatus;

it('allows ready to become claimed', function (): void {
    expect(TaskStatus::Ready->canTransitionTo(TaskStatus::Claimed))->toBeTrue();
});

it('refuses ready to jump straight to completed', function (): void {
    expect(TaskStatus::Ready->canTransitionTo(TaskStatus::Completed))->toBeFalse();
});

it('refuses any transition out of completed', function (): void {
    foreach (TaskStatus::cases() as $target) {
        expect(TaskStatus::Completed->canTransitionTo($target))->toBeFalse();
    }
});

it('treats only ready as claimable', function (): void {
    $claimable = array_values(array_filter(
        TaskStatus::cases(),
        fn (TaskStatus $s): bool => $s->isClaimable(),
    ));

    expect($claimable)->toBe([TaskStatus::Ready]);
});

it('allows a blocked task to return to the queue', function (): void {
    expect(TaskStatus::Blocked->canTransitionTo(TaskStatus::Ready))->toBeTrue();
});
