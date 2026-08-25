<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class WorkingCalendar
{
    private const MAX_DAYS_SCANNED = 400;

    /** @var Collection<int, string>|null */
    private ?Collection $holidayKeys = null;

    public function isWorkingTime(CarbonImmutable $at): bool
    {
        return $this->isWorkingDay($at) && $this->isInsideShift($at);
    }

    public function addWorkingMinutes(CarbonImmutable $from, int $minutes): CarbonImmutable
    {
        $cursor = $this->advanceToWorkingTime($from);
        $remaining = $minutes;
        $days = 0;

        while ($remaining > 0) {
            if ($days++ > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('Working calendar could not resolve a deadline; check the shift settings.');
            }

            $endOfShift = $this->shiftEnd($cursor);
            $availableToday = $cursor->diffInMinutes($endOfShift);

            if ($remaining <= $availableToday) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= $availableToday;
            $cursor = $this->advanceToWorkingTime($endOfShift->addMinute());
        }

        return $cursor;
    }

    public function workingMinutesBetween(CarbonImmutable $a, CarbonImmutable $b): int
    {
        if ($a->greaterThan($b)) {
            [$a, $b] = [$b, $a];
        }

        $cursor = $this->advanceToWorkingTime($a);
        $total = 0;

        while ($cursor->lessThan($b)) {
            $endOfShift = $this->shiftEnd($cursor);
            $segmentEnd = $endOfShift->lessThan($b) ? $endOfShift : $b;

            $total += (int) $cursor->diffInMinutes($segmentEnd);

            $cursor = $this->advanceToWorkingTime($endOfShift->addMinute());
        }

        return $total;
    }

    private function advanceToWorkingTime(CarbonImmutable $at): CarbonImmutable
    {
        $cursor = $at;
        $days = 0;

        while (true) {
            if ($days++ > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('Working calendar found no working day; check the weekend and holiday settings.');
            }

            if (! $this->isWorkingDay($cursor)) {
                $cursor = $this->startOfShiftOn($cursor->addDay());

                continue;
            }

            if ($cursor->lessThan($this->shiftStart($cursor))) {
                return $this->shiftStart($cursor);
            }

            if ($cursor->greaterThanOrEqualTo($this->shiftEnd($cursor))) {
                $cursor = $this->startOfShiftOn($cursor->addDay());

                continue;
            }

            return $cursor;
        }
    }

    private function isWorkingDay(CarbonImmutable $at): bool
    {
        $weekend = array_map('strtolower', (array) Setting::get('weekend_days', ['friday']));

        if (in_array(strtolower($at->englishDayOfWeek), $weekend, true)) {
            return false;
        }

        return ! $this->holidayKeys()->contains($at->format('Y-m-d'))
            && ! $this->holidayKeys()->contains('*-'.$at->format('m-d'));
    }

    private function isInsideShift(CarbonImmutable $at): bool
    {
        return $at->greaterThanOrEqualTo($this->shiftStart($at))
            && $at->lessThan($this->shiftEnd($at));
    }

    private function shiftStart(CarbonImmutable $on): CarbonImmutable
    {
        return $this->applyTime($on, (string) Setting::get('work_start', '09:00'));
    }

    private function shiftEnd(CarbonImmutable $on): CarbonImmutable
    {
        return $this->applyTime($on, (string) Setting::get('work_end', '17:00'));
    }

    private function startOfShiftOn(CarbonImmutable $day): CarbonImmutable
    {
        return $this->shiftStart($day);
    }

    private function applyTime(CarbonImmutable $on, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $on->setTime($hour, $minute, 0);
    }

    /** @return Collection<int, string> */
    private function holidayKeys(): Collection
    {
        return $this->holidayKeys ??= Holiday::all()->flatMap(
            fn (Holiday $holiday): array => $holiday->is_recurring
                ? ['*-'.$holiday->date->format('m-d')]
                : [$holiday->date->format('Y-m-d')]
        );
    }
}
