<?php
declare(strict_types=1);
namespace App\Services\Notifications;
use App\Events\TaskBlocked; use App\Events\TaskClaimed; use App\Events\TaskCreated; use App\Events\TaskOverdue;
use App\Models\Task; use App\Models\User; use App\Notifications\GroupedQueueNotification; use App\Notifications\TaskBlockedNotification; use App\Notifications\TaskOverdueNotification; use App\Notifications\TaskQueuedNotification;
use Illuminate\Support\Facades\Cache; use Illuminate\Support\Facades\DB;
final class NotificationDispatcher {
 private static array $pending=[];
 public function handleTaskCreated(TaskCreated $e): void { $t=$e->task; self::$pending[$t->department_id][]=$t->id; defer(fn()=> $this->flush($t->department_id,$t)); }
 public function handleTaskClaimed(TaskClaimed $e): void { $t=$e->task; User::whereHas("departments",fn($q)=>$q->where("departments.id",$t->department_id))->whereKeyNot($e->user->id)->each(fn($u)=>$u->unreadNotifications()->where("data->task_id",$t->id)->update(["read_at"=>now()])); }
 public function handleTaskBlocked(TaskBlocked $e): void { $t=$e->task; User::whereHas("departments",fn($q)=>$q->where("departments.id",$t->department_id)->where("department_user.is_supervisor",true))->whereKeyNot($e->user->id)->each(fn($u)=>$this->once($u,new TaskBlockedNotification($t),"task_blocked_{$t->id}")); }
 public function handleTaskOverdue(TaskOverdue $e): void { $t=$e->task->loadMissing("claimant"); if($t->claimant) $this->once($t->claimant,new TaskOverdueNotification($t),"task_overdue_{$t->id}"); }
 private function flush(int $dept, Task $sample): void { $ids=self::$pending[$dept]??[]; unset(self::$pending[$dept]); if(!$ids) return; $users=User::whereHas("departments",fn($q)=>$q->where("departments.id",$dept))->get(); if($users->isEmpty()) return;
  $k="burst:$dept:".now()->format("YmdHi");
  if(count($ids)>=2 && !Cache::get($k)) { Cache::put($k,true,60); $d=$sample->department; $n=new GroupedQueueNotification($dept,$d->getTranslation("name","en"),$d->getTranslation("name","ar"),count($ids)); foreach($users as $u) $this->once($u,$n,"group_$dept"); return; }
  foreach($ids as $id){ $task=Task::find($id); if(!$task) continue; foreach($users as $u) $this->once($u,new TaskQueuedNotification($task),"task_queued_$id"); }
 }
 private function once(User $u, object $n, string $key): void { if(DB::table("notifications")->where("notifiable_type",$u->getMorphClass())->where("notifiable_id",$u->id)->where("data->idempotency_key",$key)->exists()) return; $u->notify($n); }
}
