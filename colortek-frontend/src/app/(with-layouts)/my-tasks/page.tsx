import TaskListPage from "@/components/tasks/task-list-page";

export default function MyTasksPage() {
  return (
    <TaskListPage
      scope="my"
      title="My Tasks"
      description="Tasks you have claimed and are working on."
      emptyMessage="You have no claimed tasks."
    />
  );
}
