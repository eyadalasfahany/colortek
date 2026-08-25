<?php
declare(strict_types=1);
namespace App\Listeners;
use App\Events\TaskBlocked; use App\Events\TaskClaimed; use App\Events\TaskCreated; use App\Events\TaskOverdue; use App\Services\Notifications\NotificationDispatcher;
final class DispatchNotifications {
 public function __construct(private NotificationDispatcher $d) {}
 public function handleTaskCreated(TaskCreated $e): void { try{$this->d->handleTaskCreated($e);}catch(\Throwable){} }
 public function handleTaskClaimed(TaskClaimed $e): void { try{$this->d->handleTaskClaimed($e);}catch(\Throwable){} }
 public function handleTaskBlocked(TaskBlocked $e): void { try{$this->d->handleTaskBlocked($e);}catch(\Throwable){} }
 public function handleTaskOverdue(TaskOverdue $e): void { try{$this->d->handleTaskOverdue($e);}catch(\Throwable){} }
}
