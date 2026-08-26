<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

trait AuthorizesAdminAccess
{
    protected function authorizeAdmin(string $p): void
    {
        if (! auth()->user()?->can($p)) {
            abort(404);
        }
    }

    protected function authorizeAdminAny(string ...$ps): void
    {
        foreach ($ps as $p) {
            if (auth()->user()?->can($p)) {
                return;
            }
        } abort(404);
    }
}
