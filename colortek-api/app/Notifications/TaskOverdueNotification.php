<?php
declare(strict_types=1);
namespace App\Notifications; use App\Models\Task;
final class TaskOverdueNotification extends DatabaseNotification {
 public function __construct(private Task $task) {}
 public function toDatabase($n): array {
  $t=$this->task->loadMissing(["project"]);
  return $this->payload(["idempotency_key"=>"task_overdue_".$t->id,"type"=>"task.overdue",
   "message_en"=>"task.overdue: ".$t->localizedTitle("en"),"message_ar"=>"task.overdue: ".$t->localizedTitle("ar"),
   "task_id"=>$t->id,"project_id"=>$t->project_id,"project_reference"=>$t->project?->reference,
   "link"=>"task.detail","link_params"=>["id"=>$t->id]]);
 }
}
