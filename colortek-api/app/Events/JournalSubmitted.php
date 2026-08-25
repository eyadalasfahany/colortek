<?php
declare(strict_types=1);
namespace App\Events;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class JournalSubmitted implements ShouldDispatchAfterCommit { use Dispatchable, SerializesModels; public function __construct(public \App\Models\Journal $journal, public \App\Models\User $user) {} }
