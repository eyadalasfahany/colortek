<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Models\CorrectiveAction;
/** @extends BaseRepository<CorrectiveAction> */
final class CorrectiveActionRepository extends BaseRepository {
    public function __construct(){ parent::__construct(CorrectiveAction::class); }
    protected function notFoundMessage(): string { return __('Corrective action not found'); }
}
