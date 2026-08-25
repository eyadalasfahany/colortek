<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

final class SettingRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Setting::class);
    }

    public function all(): Collection
    {
        return $this->query()->orderBy('key')->get();
    }

    public function upsertValues(array $v): void
    {
        foreach ($v as $k => $val) {
            Setting::updateOrCreate(['key' => $k], ['value' => $val, 'group' => 'general']);
        }
    }

    protected function notFoundMessage(): string
    {
        return __('Setting not found');
    }
}
