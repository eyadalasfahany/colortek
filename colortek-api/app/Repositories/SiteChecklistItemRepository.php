<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SiteChecklistItem;
use Illuminate\Database\Eloquent\Builder;

final class SiteChecklistItemRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(SiteChecklistItem::class);
    }

    public function query(): Builder
    {
        return parent::query();
    }

    public function activeOrdered(): Builder
    {
        return $this->query()->where('active', true)->orderBy('sort_order');
    }

    public function ordered(): Builder
    {
        return $this->query()->orderBy('sort_order');
    }

    protected function notFoundMessage(): string
    {
        return __('Checklist item not found');
    }
}
