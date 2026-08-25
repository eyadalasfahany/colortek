<?php
declare(strict_types=1);
namespace App\Listeners;
use App\Enums\ActivitySeverity; use App\Events\ProjectCompleted; use App\Events\ProjectStageChanged; use App\Services\Activity\ActivityRecorder;
final class RecordProjectActivity {
 public function __construct(private ActivityRecorder $r) {}
 public function handleProjectStageChanged(ProjectStageChanged $e): void { $this->s(fn()=> $this->r->record("project.stage_changed",ActivitySeverity::Info,"Stage changed","تغيير المرحلة",actor:$e->user,project:$e->project,subject:$e->project)); }
 public function handleProjectCompleted(ProjectCompleted $e): void { $this->s(fn()=> $this->r->record("project.completed",ActivitySeverity::Success,"Project completed","اكتمل المشروع",actor:$e->user,project:$e->project,subject:$e->project)); }
 private function s(callable $c): void { try{$c();}catch(\Throwable){} }
}
