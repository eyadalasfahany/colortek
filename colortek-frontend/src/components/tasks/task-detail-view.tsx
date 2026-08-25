"use client";

import { ApiError } from "@/config/axios";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { TextArea } from "@/components/tailgrids/core/text-area";
import { queryKeys } from "@/lib/queryKeys";
import {
  claimTask,
  completeTask,
  getTask,
  startTask,
} from "@/services/taskService";
import type { CreatedTask, FormSchemaField } from "@/types/api";
import {
  formatAttachmentType,
  formatHandoverDueAt,
  formatStatusLabel,
  formatTaskDueAt,
  priorityLabel,
  statusBadgeColor,
} from "@/utils/task-formatters";
import { cn } from "@/utils/cn";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";

interface TaskDetailViewProps {
  taskId: number;
}

export default function TaskDetailView({ taskId }: TaskDetailViewProps) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [formValues, setFormValues] = useState<Record<string, string>>({});
  const [actionError, setActionError] = useState<string | null>(null);
  const [handoverMessage, setHandoverMessage] = useState<string | null>(null);

  const taskQuery = useQuery({
    queryKey: queryKeys.tasks.detail(taskId),
    queryFn: () => getTask(taskId),
  });

  const invalidateTaskQueries = async () => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.detail(taskId) });
    await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all() });
  };

  const claimMutation = useMutation({
    mutationFn: claimTask,
    onSuccess: invalidateTaskQueries,
    onError: (error) => setActionError(getErrorMessage(error)),
  });

  const startMutation = useMutation({
    mutationFn: startTask,
    onSuccess: invalidateTaskQueries,
    onError: (error) => setActionError(getErrorMessage(error)),
  });

  const completeMutation = useMutation({
    mutationFn: ({ id, fields }: { id: number; fields: Record<string, unknown> }) =>
      completeTask(id, { fields }),
    onSuccess: (response) => {
      const message = buildHandoverMessage(response.meta.created_tasks);
      setHandoverMessage(message);
      void invalidateTaskQueries();
    },
    onError: (error) => setActionError(getErrorMessage(error)),
  });

  const task = taskQuery.data;
  const isActionPending =
    claimMutation.isPending || startMutation.isPending || completeMutation.isPending;

  const primaryAction = useMemo(() => {
    if (!task) {
      return null;
    }

    switch (task.status) {
      case "ready":
        return { label: "Claim", action: () => claimMutation.mutate(task.id) };
      case "claimed":
        return { label: "Start", action: () => startMutation.mutate(task.id) };
      case "in_progress":
        return {
          label: "Complete",
          action: () => {
            const fields = buildFieldsPayload(task.form_schema?.fields ?? [], formValues);
            completeMutation.mutate({ id: task.id, fields });
          },
        };
      default:
        return null;
    }
  }, [claimMutation, completeMutation, formValues, startMutation, task]);

  if (taskQuery.isLoading) {
    return <TaskDetailSkeleton />;
  }

  if (taskQuery.isError || !task) {
    return (
      <div className="px-4 pt-6 lg:px-6">
        <Alert status="error">
          <AlertTitle>Task not found</AlertTitle>
          <AlertDescription>
            {taskQuery.error instanceof Error
              ? taskQuery.error.message
              : "This task is unavailable."}
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  const priority = priorityLabel(task.priority);

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-4">
        <Link href="/my-tasks" className="text-sm text-text-secondary hover:text-text-primary">
          ← Back to My Tasks
        </Link>
      </div>

      {handoverMessage ? (
        <Alert status="success" className="mb-6">
          <AlertTitle>Done.</AlertTitle>
          <AlertDescription>{handoverMessage.replace(/^Done\.\s*/, "")}</AlertDescription>
          <div className="mt-4">
            <Button
              variant="primary"
              appearance="fill"
              onPress={() => router.push("/my-tasks")}
            >
              Go to My Tasks
            </Button>
          </div>
        </Alert>
      ) : null}

      <Card className="mb-4">
        <CardHeader className="items-start gap-4">
          <div className="min-w-0 flex-1">
            <p className="text-xs font-medium uppercase tracking-wide text-text-tertiary">
              {task.reference}
            </p>
            <CardTitle className="mt-1 text-2xl">{task.title}</CardTitle>
            {task.project ? (
              <CardDescription className="mt-2">
                {task.project.reference} · {task.project.name}
                {task.project.client_name ? ` · ${task.project.client_name}` : ""}
              </CardDescription>
            ) : task.project_id ? (
              <CardDescription className="mt-2">Project #{task.project_id}</CardDescription>
            ) : null}
          </div>

          <div className="flex shrink-0 flex-col items-end gap-2">
            <Badge color={statusBadgeColor(task.status)} size="md">
              {formatStatusLabel(task.status)}
            </Badge>
            {priority ? (
              <Badge color="warning" size="sm">
                {priority}
              </Badge>
            ) : null}
            <span
              className={cn(
                "text-sm",
                task.is_overdue ? "font-medium text-error-500" : "text-text-secondary",
              )}
            >
              {formatTaskDueAt(task.due_at)}
              {task.is_overdue ? " · Overdue" : ""}
            </span>
          </div>
        </CardHeader>
      </Card>

      {task.instructions ? (
        <Card className="mb-4">
          <CardTitle className="mb-2 text-lg">What you need to do</CardTitle>
          <CardDescription className="whitespace-pre-wrap text-text-primary">
            {task.instructions}
          </CardDescription>
        </Card>
      ) : null}

      {task.previous_outputs && task.previous_outputs.length > 0 ? (
        <Card className="mb-4">
          <CardTitle className="mb-4 text-lg">What the last person did</CardTitle>
          <div className="space-y-4">
            {task.previous_outputs.map((output, index) => (
              <div key={index} className="rounded-lg border border-card-border p-4">
                {output.task_title ? (
                  <p className="text-sm font-medium text-text-primary">{output.task_title}</p>
                ) : null}
                {output.completed_by ? (
                  <p className="mt-1 text-sm text-text-secondary">By {output.completed_by}</p>
                ) : null}
                {output.fields ? (
                  <dl className="mt-3 grid gap-2 sm:grid-cols-2">
                    {Object.entries(output.fields).map(([key, value]) => (
                      <div key={key}>
                        <dt className="text-xs uppercase text-text-tertiary">{key}</dt>
                        <dd className="text-sm text-text-primary">{String(value)}</dd>
                      </div>
                    ))}
                  </dl>
                ) : null}
              </div>
            ))}
          </div>
        </Card>
      ) : null}

      {task.form_schema?.fields && task.form_schema.fields.length > 0 ? (
        <Card className="mb-4">
          <CardTitle className="mb-4 text-lg">The form</CardTitle>
          <div className="space-y-4">
            {task.form_schema.fields.map((field) => (
              <DynamicField
                key={field.name}
                field={field}
                value={formValues[field.name] ?? ""}
                onChange={(value) =>
                  setFormValues((current) => ({ ...current, [field.name]: value }))
                }
              />
            ))}
          </div>
        </Card>
      ) : null}

      {task.required_attachment_types && task.required_attachment_types.length > 0 ? (
        <Card className="mb-4">
          <CardTitle className="mb-4 text-lg">Files</CardTitle>
          <ul className="space-y-2">
            {task.required_attachment_types.map((type) => (
              <li
                key={type}
                className="flex items-center justify-between rounded-lg border border-card-border px-3 py-2 text-sm"
              >
                <span className="text-text-primary">{formatAttachmentType(type)}</span>
                <span className="text-error-500">Required</span>
              </li>
            ))}
          </ul>
        </Card>
      ) : null}

      {actionError ? (
        <Alert status="error" className="mb-4">
          <AlertTitle>Action failed</AlertTitle>
          <AlertDescription>{actionError}</AlertDescription>
        </Alert>
      ) : null}

      {!handoverMessage && primaryAction ? (
        <div className="flex gap-3">
          <Button
            variant="primary"
            appearance="fill"
            size="lg"
            isDisabled={isActionPending}
            onPress={primaryAction.action}
          >
            {isActionPending ? "Working…" : primaryAction.label}
          </Button>
        </div>
      ) : null}

      {!handoverMessage && !primaryAction && task.status === "completed" ? (
        <Alert status="success">
          <AlertTitle>Completed</AlertTitle>
          <AlertDescription>This task is already completed.</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}

function DynamicField({
  field,
  value,
  onChange,
}: {
  field: FormSchemaField;
  value: string;
  onChange: (value: string) => void;
}) {
  const label = (
    <Label>
      {field.label}
      {field.required ? <span className="text-error-500"> *</span> : null}
    </Label>
  );

  if (field.type === "textarea") {
    return (
      <div className="flex flex-col gap-1.5">
        {label}
        <TextArea
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="min-h-24 w-full"
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1.5">
      {label}
      <Input
        type={field.type === "number" ? "number" : "text"}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="w-full px-3 py-2.5 text-sm"
      />
    </div>
  );
}

function TaskDetailSkeleton() {
  return (
    <div className="px-4 pt-6 lg:px-6">
      <Skeleton className="h-4 w-32" />
      <Card className="mt-4">
        <Skeleton className="h-4 w-24" />
        <Skeleton className="mt-3 h-8 w-2/3" />
        <Skeleton className="mt-2 h-4 w-1/2" />
      </Card>
    </div>
  );
}

function buildFieldsPayload(
  fields: FormSchemaField[],
  values: Record<string, string>,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {};

  for (const field of fields) {
    const rawValue = values[field.name];
    if (rawValue === undefined || rawValue === "") {
      continue;
    }

    payload[field.name] = field.type === "number" ? Number(rawValue) : rawValue;
  }

  return payload;
}

function buildHandoverMessage(createdTasks: CreatedTask[]): string {
  if (createdTasks.length === 0) {
    return "Done.";
  }

  const created = createdTasks[0];
  const department = created.department ?? "The next team";
  const dueText = formatHandoverDueAt(created.due_at);

  return `Done. ${department} now has "${created.title}"${dueText ? `, ${dueText}` : ""}.`;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.errors) {
      const firstError = Object.values(error.errors)[0]?.[0];
      if (firstError) {
        return firstError;
      }
    }

    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Something went wrong.";
}
