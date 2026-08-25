import { format, isToday, isTomorrow } from "date-fns";
import type { TaskPriority, TaskStatus } from "@/types/api";

type BadgeColor =
  | "gray"
  | "primary"
  | "error"
  | "warning"
  | "success"
  | "cyan"
  | "sky"
  | "blue"
  | "violet"
  | "purple"
  | "pink"
  | "rose"
  | "orange";

export function formatTaskDueAt(dueAt: string | null): string {
  if (!dueAt) {
    return "No deadline";
  }

  const date = new Date(dueAt);

  if (isToday(date)) {
    return `Due today at ${format(date, "h:mm a")}`;
  }

  if (isTomorrow(date)) {
    return `Due tomorrow at ${format(date, "h:mm a")}`;
  }

  return `Due ${format(date, "d MMM yyyy, h:mm a")}`;
}

export function formatHandoverDueAt(dueAt: string | null): string {
  if (!dueAt) {
    return "";
  }

  const date = new Date(dueAt);

  if (isToday(date)) {
    return `due today at ${format(date, "h:mm a")}`;
  }

  if (isTomorrow(date)) {
    return `due tomorrow at ${format(date, "h:mm a")}`;
  }

  return `due ${format(date, "d MMM yyyy, h:mm a")}`;
}

export function statusBadgeColor(status: TaskStatus): BadgeColor {
  switch (status) {
    case "ready":
      return "primary";
    case "claimed":
      return "blue";
    case "in_progress":
      return "success";
    case "paused":
      return "warning";
    case "blocked":
      return "error";
    case "completed":
      return "gray";
    default:
      return "gray";
  }
}

export function priorityLabel(priority: TaskPriority): string | null {
  if (priority === "normal" || priority === "low") {
    return null;
  }

  return priority.charAt(0).toUpperCase() + priority.slice(1);
}

export function formatStatusLabel(status: TaskStatus): string {
  return status.replaceAll("_", " ");
}

export function formatAttachmentType(type: string): string {
  return type.replaceAll("_", " ");
}
