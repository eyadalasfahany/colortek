<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class FailedJobService
{
    public function paginate(int $per = 15): LengthAwarePaginator
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->paginate($per);
    }

    public function retry(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
    }
}
