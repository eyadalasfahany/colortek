<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\User;
use App\Services\Projects\ProjectVisibility;
use Illuminate\Http\Request;

final class ProjectFilter
{
    public function __construct(private ProjectVisibility $v) {}

    public function apply(Request $r, $q, User $u)
    {
        $this->v->applyToProjects($q, $u);
        if ($r->filled('stage')) {
            $q->where('stage', $r->string('stage')->toString());
        }
        if ($r->filled('status')) {
            $q->where('status', $r->string('status')->toString());
        }
        if ($r->boolean('blocked')) {
            $q->whereHas('tasks', fn ($t) => $t->where('status', 'blocked'));
        }
        if ($r->boolean('overdue')) {
            $q->whereHas('tasks', fn ($t) => $t->where('is_overdue', true));
        }
        if ($s = $r->string('q')->toString()) {
            $q->where(fn ($b) => $b->where('reference', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        }

        return $q->orderByDesc('updated_at');
    }
}
