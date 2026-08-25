import TaskListPage from "@/components/tasks/task-list-page";

export default function QueuePage() {
  return (
    <TaskListPage
      scope="queue"
      title="Queue"
      description="Ready tasks in your departments."
      emptyMessage="No tasks in the queue right now."
    />
  );
}
