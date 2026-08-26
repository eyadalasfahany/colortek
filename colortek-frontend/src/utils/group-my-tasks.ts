import type { TaskListItem } from "@/types/api";

export type MyTaskGroupKey = "overdue" | "today" | "blocked" | "other";

export function groupMyTasks(tasks: TaskListItem[]) {
  const overdue: TaskListItem[] = [];
  const today: TaskListItem[] = [];
  const blocked: TaskListItem[] = [];
  const other: TaskListItem[] = [];

  const now = new Date();
  const startOfTomorrow = new Date(now);
  startOfTomorrow.setHours(24, 0, 0, 0);

  for (const task of tasks) {
    if (task.status === "blocked") {
      blocked.push(task);
      continue;
    }

    if (task.is_overdue) {
      overdue.push(task);
      continue;
    }

    if (task.due_at) {
      const due = new Date(task.due_at);
      if (due < startOfTomorrow) {
        today.push(task);
        continue;
      }
    }

    other.push(task);
  }

  return (
    [
      { key: "overdue" as const, tasks: overdue },
      { key: "today" as const, tasks: today },
      { key: "blocked" as const, tasks: blocked },
      { key: "other" as const, tasks: other },
    ] satisfies Array<{ key: MyTaskGroupKey; tasks: TaskListItem[] }>
  ).filter((group) => group.tasks.length > 0);
}
