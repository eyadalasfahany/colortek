import type { TaskListItem } from "@/types/api";

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

  return [
    { key: "overdue", label: "Overdue", tasks: overdue },
    { key: "today", label: "Today", tasks: today },
    { key: "blocked", label: "Blocked", tasks: blocked },
    { key: "other", label: "Everything else", tasks: other },
  ].filter((group) => group.tasks.length > 0);
}
