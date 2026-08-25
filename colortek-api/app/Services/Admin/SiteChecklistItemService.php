<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\SiteChecklistItem;
use App\Models\User;
use App\Repositories\SiteChecklistItemRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SiteChecklistItemService
{
    public function __construct(private SiteChecklistItemRepository $repo, private AuditLogger $audit) {}

    public function paginate(int $per = 15): LengthAwarePaginator
    {
        return $this->repo->paginate($this->repo->ordered(), $per);
    }

    public function activeOptions(): Collection
    {
        return $this->repo->activeOrdered()->get();
    }

    public function store(array $d, User $u): SiteChecklistItem
    {
        return DB::transaction(function () use ($d, $u): SiteChecklistItem {
            /** @var SiteChecklistItem $item */
            $item = $this->repo->create($d + ['active' => $d['active'] ?? true]);
            $this->audit->log($item, 'created', $u, null, $d);

            return $item;
        });
    }

    public function update(SiteChecklistItem $i, array $d, User $u): SiteChecklistItem
    {
        $old = $i->only(['label_en', 'label_ar', 'is_readiness_critical', 'sort_order', 'active']);

        return DB::transaction(function () use ($i, $d, $u, $old): SiteChecklistItem {
            /** @var SiteChecklistItem $item */
            $item = $this->repo->update($i, collect($d)->except('code')->all());
            $this->audit->log($item, 'updated', $u, $old, $d);

            return $item;
        });
    }

    public function findOrFail(int $id): SiteChecklistItem
    {
        /** @var SiteChecklistItem $record */
        $record = $this->repo->findOneOrFail($id);

        return $record;
    }
}
