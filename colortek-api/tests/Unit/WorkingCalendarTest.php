<?php

declare(strict_types=1);

use App\Models\Holiday;
use App\Models\Setting;
use App\Services\Time\WorkingCalendar;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    Setting::updateOrCreate(['key' => 'work_start'], ['value' => '09:00']);
    Setting::updateOrCreate(['key' => 'work_end'], ['value' => '17:00']);
    Setting::updateOrCreate(['key' => 'weekend_days'], ['value' => ['friday']]);

    $this->calendar = app(WorkingCalendar::class);
});

it('knows a Wednesday mid-morning is working time', function (): void {
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-02 10:00', 'Africa/Cairo')))
        ->toBeTrue();
});

it('knows before the shift starts is not working time', function (): void {
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-02 08:59', 'Africa/Cairo')))
        ->toBeFalse();
});

it('knows Friday is not working time', function (): void {
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-04 10:00', 'Africa/Cairo')))
        ->toBeFalse();
});

it('adds working minutes inside one day', function (): void {
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-02 10:00', 'Africa/Cairo'), 120
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-02 12:00');
});

it('rolls a four hour deadline started at 15:00 on Thursday over the Friday weekend', function (): void {
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-03 15:00', 'Africa/Cairo'), 240
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-05 11:00');
});

it('skips a holiday as well as the weekend', function (): void {
    Holiday::create([
        'date' => '2026-09-05',
        'name' => ['en' => 'Test holiday', 'ar' => 'إجازة'],
        'type' => 'public',
        'is_recurring' => false,
    ]);

    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-03 15:00', 'Africa/Cairo'), 240
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-06 11:00');
});

it('starts counting the next working morning when begun after the shift', function (): void {
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-02 19:00', 'Africa/Cairo'), 60
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-03 10:00');
});

it('counts one working day as eight hours', function (): void {
    $minutes = $this->calendar->workingMinutesBetween(
        CarbonImmutable::parse('2026-09-02 09:00', 'Africa/Cairo'),
        CarbonImmutable::parse('2026-09-03 09:00', 'Africa/Cairo'),
    );

    expect($minutes)->toBe(480);
});

it('applies a recurring holiday in a later year', function (): void {
    Holiday::create([
        'date' => '2025-09-05',
        'name' => ['en' => 'Recurring', 'ar' => 'متكرر'],
        'type' => 'company',
        'is_recurring' => true,
    ]);

    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-05 10:00', 'Africa/Cairo')))
        ->toBeFalse();
});
