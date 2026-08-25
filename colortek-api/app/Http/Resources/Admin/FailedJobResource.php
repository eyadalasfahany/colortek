<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

final class FailedJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        $j = $this->resource;

        return ['id' => $j->id, 'uuid' => $j->uuid, 'connection' => $j->connection, 'queue' => $j->queue, 'exception' => $j->exception, 'failed_at' => $j->failed_at];
    }
}
