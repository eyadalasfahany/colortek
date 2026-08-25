<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\CorrectiveAction; use App\Models\User;
final class CorrectiveActionPolicy {
 public function viewAny(User $u): bool { return $u->can('site.view'); }
 public function view(User $u, CorrectiveAction $a): bool { return $u->can('site.view'); }
 public function update(User $u, CorrectiveAction $a): bool { return $u->can('site.corrective_action_manage'); }
}
