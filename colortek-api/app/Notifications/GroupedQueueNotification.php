<?php
declare(strict_types=1);
namespace App\Notifications;
final class GroupedQueueNotification extends DatabaseNotification {
 public function __construct(private int $deptId, private string $en, private string $ar, private int $count) {}
 public function toDatabase($n): array {
  return $this->payload(["idempotency_key"=>"grouped_{$this->deptId}_".now()->format("YmdHi"),"type"=>"task.grouped_queue",
   "message_en"=>"{$this->count} new tasks in {$this->en}","message_ar"=>"{$this->count} مهام في {$this->ar}",
   "department_id"=>$this->deptId,"count"=>$this->count,"link"=>"queue","link_params"=>["department_id"=>$this->deptId]]);
 }
}
