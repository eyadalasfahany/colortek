<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Attachment;

/** @extends BaseRepository<Attachment> */
final class AttachmentRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Attachment::class);
    }

    protected function notFoundMessage(): string
    {
        return __('Attachment not found');
    }
}
