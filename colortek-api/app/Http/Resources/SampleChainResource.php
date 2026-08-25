<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array{count: int, attempts: list<array<string, mixed>>} $resource */
final class SampleChainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'count' => $this->resource['count'],
            'attempts' => $this->resource['attempts'],
        ];
    }
}
