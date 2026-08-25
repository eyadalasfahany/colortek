import { differenceInCalendarDays, differenceInHours, format, isToday, isTomorrow } from "date-fns";
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

export function formatDeadlineInWords(dueAt: string | null, isOverdue = false): string {
  if (!dueAt) {
    return "No deadline";
  }

  const date = new Date(dueAt);
  const now = new Date();
  const hours = differenceInHours(date, now);
  const days = differenceInCalendarDays(date, now);

  if (isOverdue || hours < 0) {
    const lateHours = Math.abs(hours);
    if (lateHours < 24) {
      return `${Math.max(1, lateHours)} hour${lateHours === 1 ? "" : "s"} late`;
    }

    const lateDays = Math.abs(days);
    return `${Math.max(1, lateDays)} day${lateDays === 1 ? "" : "s"} late`;
  }

  if (hours <= 24) {
    return `Due in ${Math.max(1, hours)} hour${hours === 1 ? "" : "s"}`;
  }

  if (days === 1) {
    return "Due tomorrow";
  }

  if (days <= 7) {
    return `Due in ${days} days`;
  }

  return formatTaskDueAt(dueAt);
}

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
