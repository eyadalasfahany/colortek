<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Claimed = 'claimed';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Ready, self::Cancelled],
            self::Waiting => [self::Ready, self::Cancelled],
            self::Ready => [self::Claimed, self::Pending, self::Cancelled],
            self::Claimed => [self::InProgress, self::Ready, self::Blocked, self::Cancelled],
            self::InProgress => [self::Paused, self::Blocked, self::Completed, self::Cancelled],
            self::Paused => [self::InProgress, self::Blocked, self::Cancelled],
            self::Blocked => [self::InProgress, self::Paused, self::Ready, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isClaimable(): bool
    {
        return $this === self::Ready;
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $labels = [
            'en' => [
                'pending' => 'Pending',
                'waiting' => 'Waiting',
                'ready' => 'Ready',
                'claimed' => 'Claimed',
                'in_progress' => 'In progress',
                'paused' => 'Paused',
                'blocked' => 'Blocked',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'ar' => [
                'pending' => 'معلقة',
                'waiting' => 'في الانتظار',
                'ready' => 'جاهزة',
                'claimed' => 'محجوزة',
                'in_progress' => 'قيد التنفيذ',
                'paused' => 'متوقفة',
                'blocked' => 'محظورة',
                'completed' => 'مكتملة',
                'cancelled' => 'ملغاة',
            ],
        ];

        return $labels[$locale][$this->value] ?? $labels['en'][$this->value];
    }
}
