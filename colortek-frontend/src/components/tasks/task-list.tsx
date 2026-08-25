"use client";

import { ApiError } from "@/config/axios";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import type { TaskListItem } from "@/types/api";
import {
  formatDeadlineInWords,
  formatStatusLabel,
  priorityLabel,
  statusBadgeColor,
} from "@/utils/task-formatters";
import Link from "next/link";
import { cn } from "@/utils/cn";
import { groupMyTasks } from "@/utils/group-my-tasks";

interface TaskListProps {
  tasks: TaskListItem[];
  emptyMessage: string;
  showClaimButton?: boolean;
  claimingTaskId?: number | null;
  onClaim?: (taskId: number) => void;
}

export function GroupedTaskList({
  tasks,
  emptyMessage,
}: {
  tasks: TaskListItem[];
  emptyMessage: string;
}) {
  if (tasks.length === 0) {
    return (
      <Card>
        <CardDescription className="text-center text-text-secondary">{emptyMessage}</CardDescription>
      </Card>
    );
  }

  const groups = groupMyTasks(tasks);

  return (
    <div className="space-y-6">
      {groups.map((group) => (
        <section key={group.key}>
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-text-tertiary">
            {group.label}
          </h2>
          <div className="flex flex-col gap-3">
            {group.tasks.map((task) => (
              <TaskListItemCard key={task.id} task={task} showClaimButton={false} isClaiming={false} />
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}

export function TaskList({
  tasks,
  emptyMessage,
  showClaimButton = false,
  claimingTaskId = null,
  onClaim,
}: TaskListProps) {
  if (tasks.length === 0) {
    return (
      <Card>
        <CardDescription className="text-center text-text-secondary">{emptyMessage}</CardDescription>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {tasks.map((task) => (
        <TaskListItemCard
          key={task.id}
          task={task}
          showClaimButton={showClaimButton}
          isClaiming={claimingTaskId === task.id}
          onClaim={onClaim}
        />
      ))}
    </div>
  );
}

function TaskListItemCard({
  task,
  showClaimButton,
  isClaiming,
  onClaim,
}: {
  task: TaskListItem;
  showClaimButton: boolean;
  isClaiming: boolean;
  onClaim?: (taskId: number) => void;
}) {
  const priority = priorityLabel(task.priority);
  const canClaim = showClaimButton && task.status === "ready" && onClaim;

  return (
    <Card className="transition hover:border-brand-200 hover:shadow-sm">
      <CardHeader className="items-start gap-4">
        <Link href={`/tasks/${task.id}`} className="min-w-0 flex-1">
          <p className="text-xs font-medium uppercase tracking-wide text-text-tertiary">
            {task.reference}
          </p>
          <CardTitle className="mt-1 text-base">{task.title}</CardTitle>
          <CardDescription className="mt-2 flex flex-wrap items-center gap-2">
            {task.department ? <span>{task.department.name}</span> : null}
            {task.claimant ? <span>Claimed by {task.claimant.name}</span> : null}
          </CardDescription>
        </Link>

        <div className="flex shrink-0 flex-col items-end gap-2">
          <Badge color={statusBadgeColor(task.status)} size="sm">
            {formatStatusLabel(task.status)}
          </Badge>
          {priority ? (
            <Badge color="warning" size="sm">
              {priority}
            </Badge>
          ) : null}
          <span
            className={cn(
              "text-xs",
              task.is_overdue ? "font-medium text-error-500" : "text-text-tertiary",
            )}
          >
            {formatDeadlineInWords(task.due_at, task.is_overdue)}
          </span>
          {canClaim ? (
            <Button
              variant="primary"
              appearance="fill"
              size="sm"
              isDisabled={isClaiming}
              onPress={() => onClaim(task.id)}
            >
              {isClaiming ? "Claiming…" : "Claim"}
            </Button>
          ) : null}
        </div>
      </CardHeader>
    </Card>
  );
}

export function TaskListSkeleton() {
  return (
    <div className="flex flex-col gap-3">
      {Array.from({ length: 4 }).map((_, index) => (
        <Card key={index}>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-5 w-2/3" />
          <Skeleton className="mt-2 h-4 w-1/3" />
        </Card>
      ))}
    </div>
  );
}

export function getClaimErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Could not claim this task.";
}

