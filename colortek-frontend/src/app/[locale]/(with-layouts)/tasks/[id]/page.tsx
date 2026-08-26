import TaskDetailView from "@/components/tasks/task-detail-view";

type TaskDetailPageProps = {
  params: Promise<{ id: string }>;
};

export default async function TaskDetailPage({ params }: TaskDetailPageProps) {
  const { id } = await params;
  const taskId = Number(id);

  if (!Number.isFinite(taskId)) {
    return null;
  }

  return <TaskDetailView taskId={taskId} />;
}
