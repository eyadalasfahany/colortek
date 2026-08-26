"use client";

import {
  GroupedTaskList,
  TaskListSkeleton,
} from "@/components/tasks/task-list";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { queryKeys } from "@/lib/queryKeys";
import { getTasks } from "@/services/taskService";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";

export default function MyTasksPage() {
  const t = useTranslations("tasks");
  const tStates = useTranslations("states");
  const tasksQuery = useQuery({
    queryKey: queryKeys.tasks.list("my"),
    queryFn: () => getTasks({ scope: "my", per_page: 100 }),
  });

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">{t("myTasksTitle")}</h1>
        <p className="mt-1 text-sm text-text-secondary">{t("myTasksDescription")}</p>
      </div>

      {tasksQuery.isLoading ? <TaskListSkeleton /> : null}

      {tasksQuery.isError ? (
        <Alert status="error">
          <AlertTitle>{tStates("error")}</AlertTitle>
          <AlertDescription>
            {tasksQuery.error instanceof Error
              ? tasksQuery.error.message
              : tStates("error")}
          </AlertDescription>
        </Alert>
      ) : null}

      {tasksQuery.isSuccess ? (
        <GroupedTaskList
          tasks={tasksQuery.data.data}
          emptyMessage={t("myTasksEmpty")}
        />
      ) : null}
    </div>
  );
}
