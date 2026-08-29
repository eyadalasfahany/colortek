<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Data;

/** The outcome of a push. `simulated` is Phase 1's success. */
final readonly class PushResult
{
    private function __construct(
        public bool $success,
        public string $status,
        public string $idempotencyKey,
        public ?string $odooReference = null,
        public ?string $error = null,
    ) {}

    public static function simulated(string $idempotencyKey): self
    {
        return new self(true, 'simulated', $idempotencyKey);
    }

    public static function sent(string $idempotencyKey, ?string $odooReference = null): self
    {
        return new self(true, 'sent', $idempotencyKey, $odooReference);
    }

    public static function failed(string $idempotencyKey, string $error): self
    {
        return new self(false, 'failed', $idempotencyKey, null, $error);
    }

    /** A push already recorded under this key; the retry was a no-op. */
    public static function duplicate(string $idempotencyKey, ?string $odooReference = null): self
    {
        return new self(true, 'duplicate', $idempotencyKey, $odooReference);
    }
}
