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
import { ClientDecisionPanel } from "@/components/tasks/client-decision-panel";
import { EmployeePickerField } from "@/components/tasks/employee-picker-field";
import { FormulaRegistrationPanel } from "@/components/tasks/formula-registration-panel";
import { PaymentSubjectPanel } from "@/components/tasks/payment-subject-panel";
import { SampleSubjectPanel } from "@/components/tasks/sample-subject-panel";
import { TaskActionsBar } from "@/components/tasks/task-actions-bar";
import { TaskActivitySection } from "@/components/tasks/task-activity-section";
import { queryKeys } from "@/lib/queryKeys";
import { uploadAttachment, type UploadedAttachment } from "@/services/attachmentService";
import {
  claimTask,
  completeTask,
  getTask,
  startTask,
} from "@/services/taskService";
import type { CreatedTask, FormSchemaField } from "@/types/api";
import { isPaymentSubjectContext } from "@/types/api";
import { isSampleSubjectContext } from "@/types/samples";
import { getDecidedAtFieldLabel, resolveTaskCode } from "@/utils/task-codes";
import {
  formatAttachmentType,
  formatDeadlineInWords,
  formatHandoverDueAt,
  formatStatusLabel,
  priorityLabel,
  statusBadgeColor,
} from "@/utils/task-formatters";
import { cn } from "@/utils/cn";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { Link, useRouter } from "@/i18n/navigation";

interface TaskDetailViewProps {
  taskId: number;
}

export default function TaskDetailView({ taskId }: TaskDetailViewProps) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const tActions = useTranslations("actions");
  const tTasks = useTranslations("tasks");
  const [formValues, setFormValues] = useState<Record<string, string>>({});
  const [uploadedAttachments, setUploadedAttachments] = useState<
    Record<string, UploadedAttachment[]>
  >({});
  const [uploadingType, setUploadingType] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [handoverMessage, setHandoverMessage] = useState<string | null>(null);
  const [comments, setComments] = useState<
    Array<{ id: string; body: string; created_at: string; author?: string }>
  >([]);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

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
    onError: (error) => setActionError(getErrorMessage(error, tTasks("genericError"))),
  });

  const startMutation = useMutation({
    mutationFn: startTask,
    onSuccess: invalidateTaskQueries,
    onError: (error) => setActionError(getErrorMessage(error, tTasks("genericError"))),
  });

  const completeMutation = useMutation({
    mutationFn: ({
      id,
      fields,
      attachmentIds,
    }: {
      id: number;
      fields: Record<string, unknown>;
      attachmentIds?: Record<string, number[]>;
    }) => completeTask(id, { fields, attachment_ids: attachmentIds }),
    onSuccess: (response) => {
      const message = buildHandoverMessage(response.meta.created_tasks);
      setHandoverMessage(message);
      void invalidateTaskQueries();
    },
    onError: (error) => setActionError(getErrorMessage(error, tTasks("genericError"))),
  });

  const task = taskQuery.data;
  const taskCode = task ? resolveTaskCode(task) : null;
  const paymentSubject = task?.subject && isPaymentSubjectContext(task.subject) ? task.subject : null;
  const sampleSubject = task?.subject && isSampleSubjectContext(task.subject) ? task.subject : null;
  const isActionPending =
    claimMutation.isPending || startMutation.isPending || completeMutation.isPending;

  const missingRequiredAttachments = useMemo(() => {
    if (!task?.required_attachment_types?.length) return [];
    return task.required_attachment_types.filter(
      (type) => (uploadedAttachments[type] ?? []).length === 0,
    );
  }, [task, uploadedAttachments]);

  const missingRequiredFields = useMemo(() => {
    if (!task?.form_schema?.fields) return [];
    return task.form_schema.fields.filter(
      (field) => field.required && !(formValues[field.name] ?? "").trim(),
    );
  }, [formValues, task]);

  const primaryAction = useMemo(() => {
    if (!task) {
      return null;
    }

    switch (task.status) {
      case "ready":
        return { label: tActions("claim"), action: () => claimMutation.mutate(task.id), disabled: false };
      case "claimed":
        return { label: tActions("start"), action: () => startMutation.mutate(task.id), disabled: false };
      case "in_progress":
        return {
          label: tActions("complete"),
          action: () => {
            setFieldErrors({});
            if (missingRequiredFields.length > 0) {
              const errors: Record<string, string> = {};
              for (const field of missingRequiredFields) {
                errors[field.name] = tTasks("fieldRequired", { label: field.label });
              }
              setFieldErrors(errors);
              setActionError(
                tTasks("fillRequiredFields", {
                  fields: missingRequiredFields.map((f) => f.label).join(", "),
                }),
              );
              return;
            }
            if (missingRequiredAttachments.length > 0) {
              setActionError(
                tTasks("uploadRequiredFiles", {
                  files: missingRequiredAttachments.map(formatAttachmentType).join(", "),
                }),
              );
              return;
            }
            const fields = buildFieldsPayload(task.form_schema?.fields ?? [], formValues);
            const attachmentIds = buildAttachmentIds(
              task.required_attachment_types ?? [],
              uploadedAttachments,
            );
            completeMutation.mutate({ id: task.id, fields, attachmentIds });
          },
          disabled: false,
        };
      default:
        return null;
    }
  }, [
    claimMutation,
    completeMutation,
    formValues,
    missingRequiredAttachments,
    missingRequiredFields,
    startMutation,
    tActions,
    tTasks,
    task,
    uploadedAttachments,
  ]);

  if (taskQuery.isLoading) {
    return <TaskDetailSkeleton />;
  }

  if (taskQuery.isError || !task) {
    return (
      <div className="px-4 pt-6 lg:px-6">
        <Alert status="error">
          <AlertTitle>{tTasks("notFound")}</AlertTitle>
          <AlertDescription>
            {taskQuery.error instanceof Error
              ? taskQuery.error.message
              : tTasks("notFound")}
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  const priority = priorityLabel(task.priority);

  async function handleAttachmentUpload(type: string, file: File) {
    setActionError(null);
    setUploadingType(type);

    try {
      const attachment = await uploadAttachment(file, type);
      setUploadedAttachments((current) => ({
        ...current,
        [type]: [...(current[type] ?? []), attachment],
      }));
    } catch (error) {
      setActionError(getErrorMessage(error, tTasks("genericError")));
    } finally {
      setUploadingType(null);
    }
  }

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-4">
        <Link href="/my-tasks" className="text-sm text-text-secondary hover:text-text-primary">
          ← {tTasks("backToMyTasks")}
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

      {task.status === "in_progress" && task.started_at ? (
        <Card className="mb-4 border-brand-200 bg-brand-50">
          <p className="text-sm font-medium text-text-secondary">Timer running</p>
          <p className="text-3xl font-bold text-brand-600">
            {formatElapsedSince(task.started_at)}
          </p>
        </Card>
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
              {formatDeadlineInWords(task.due_at, task.is_overdue)}
              {task.is_overdue ? ` · ${tTasks("overdue")}` : ""}
            </span>
          </div>
        </CardHeader>
      </Card>

      {task.instructions ? (
        <Card className="mb-4">
          <CardTitle className="mb-2 text-lg">{tTasks("instructions")}</CardTitle>
          <CardDescription className="whitespace-pre-wrap text-text-primary">
            {task.instructions}
          </CardDescription>
        </Card>
      ) : null}

      {paymentSubject ? <PaymentSubjectPanel subject={paymentSubject} /> : null}

      {sampleSubject ? <SampleSubjectPanel subject={sampleSubject} /> : null}

      {sampleSubject && taskCode === "reception_register_formula" ? <FormulaRegistrationPanel subject={sampleSubject} /> : null}

      {sampleSubject && taskCode === "sales_get_client_decision" ? <ClientDecisionPanel subject={sampleSubject} /> : null}

      {sampleSubject ? (
        <div className="mb-4">
          <Link href={`/samples/${sampleSubject.reference}`} className="text-sm text-brand-500 hover:text-brand-600">View sample {sampleSubject.reference}</Link>
        </div>
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
                taskCode={taskCode}
                value={formValues[field.name] ?? ""}
                error={fieldErrors[field.name]}
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
          <ul className="space-y-3">
            {task.required_attachment_types.map((type) => {
              const uploads = uploadedAttachments[type] ?? [];
              const isUploading = uploadingType === type;

              return (
                <li
                  key={type}
                  className="rounded-lg border border-card-border px-3 py-3 text-sm"
                >
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-text-primary">{formatAttachmentType(type)}</span>
                    <span className={uploads.length > 0 ? "text-success-500" : "text-error-500"}>
                      {uploads.length > 0 ? "Uploaded" : "Required"}
                    </span>
                  </div>

                  {uploads.length > 0 ? (
                    <ul className="mt-2 space-y-1 text-text-secondary">
                      {uploads.map((attachment) => (
                        <li key={attachment.id}>{attachment.filename}</li>
                      ))}
                    </ul>
                  ) : null}

                  {task.status === "in_progress" ? (
                    <div className="mt-3">
                      <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-brand-500 hover:text-brand-600">
                        <input
                          type="file"
                          className="hidden"
                          disabled={isUploading || isActionPending}
                          onChange={(event) => {
                            const file = event.target.files?.[0];
                            if (!file) {
                              return;
                            }

                            void handleAttachmentUpload(type, file);
                            event.target.value = "";
                          }}
                        />
                        {isUploading ? "Uploading…" : "Choose file"}
                      </label>
                    </div>
                  ) : null}
                </li>
              );
            })}
          </ul>
        </Card>
      ) : null}

      {actionError ? (
        <Alert status="error" className="mb-4">
          <AlertTitle>{tTasks("actionFailed")}</AlertTitle>
          <AlertDescription>{actionError}</AlertDescription>
        </Alert>
      ) : null}

      {!handoverMessage ? (
        <TaskActionsBar
          task={task}
          isActionPending={isActionPending}
          onSuccess={() => void invalidateTaskQueries()}
          onError={setActionError}
          onCommentAdded={(comment) => setComments((current) => [comment, ...current])}
        />
      ) : null}

      {!handoverMessage && primaryAction ? (
        <div className="flex flex-col gap-2">
          {(missingRequiredAttachments.length > 0 || missingRequiredFields.length > 0) &&
          task.status === "in_progress" ? (
            <p className="text-sm text-text-secondary">
              {missingRequiredFields.length > 0
                ? tTasks("requiredFieldsLabel", {
                    fields: missingRequiredFields.map((f) => f.label).join(", "),
                  })
                : null}
              {missingRequiredAttachments.length > 0
                ? tTasks("requiredFilesLabel", {
                    files: missingRequiredAttachments.map(formatAttachmentType).join(", "),
                  })
                : null}
            </p>
          ) : null}
          <div className="flex gap-3">
          <Button
            variant="primary"
            appearance="fill"
            size="lg"
            isDisabled={isActionPending}
            onPress={primaryAction.action}
          >
            {isActionPending ? tTasks("working") : primaryAction.label}
          </Button>
          </div>
        </div>
      ) : null}

      <TaskActivitySection task={task} comments={comments} />

      {!handoverMessage && !primaryAction && task.status === "completed" ? (
        <Alert status="success">
          <AlertTitle>{tActions("complete")}</AlertTitle>
          <AlertDescription>{tTasks("alreadyCompleted")}</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}

function DynamicField({
  field,
  taskCode,
  value,
  error,
  onChange,
}: {
  field: FormSchemaField;
  taskCode: string | null;
  value: string;
  error?: string;
  onChange: (value: string) => void;
}) {
  const labelText = getDecidedAtFieldLabel(field);
  const label = (
    <Label>
      {labelText}
      {field.required ? <span className="text-error-500"> *</span> : null}
    </Label>
  );

  if (field.type === "employee" || (taskCode === "tinting_author_formula" && field.name === "author_employee_id")) {
    return <EmployeePickerField value={value} onChange={onChange} required={field.required} />;
  }

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

  if (field.type === "boolean") {
    return (
      <div className="flex items-center gap-2">
        <input id={field.name} type="checkbox" checked={value === "true"} onChange={(e) => onChange(e.target.checked ? "true" : "false")} className="size-4 rounded border-card-border" />
        <Label htmlFor={field.name}>{labelText}{field.required ? <span className="text-error-500"> *</span> : null}</Label>
      </div>
    );
  }
  if (field.type === "select" && field.options) {
    return (
      <div className="flex flex-col gap-1.5">{label}<select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm"><option value="">Select…</option>{field.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select></div>
    );
  }
  if (field.type === "date") {
    return (
      <div className="flex flex-col gap-1.5">{label}<Input type="date" value={value} onChange={(e) => onChange(e.target.value)} className="w-full px-3 py-2.5 text-sm" /></div>
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
      {error ? <p className="text-xs text-error-500">{error}</p> : null}
    </div>
  );
}

function formatElapsedSince(startedAt: string): string {
  const start = new Date(startedAt).getTime();
  const elapsedMs = Date.now() - start;
  const totalMinutes = Math.max(0, Math.floor(elapsedMs / 60000));
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return `${hours}h ${minutes}m`;
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

function buildAttachmentIds(
  requiredTypes: string[],
  uploads: Record<string, UploadedAttachment[]>,
): Record<string, number[]> | undefined {
  const attachmentIds: Record<string, number[]> = {};

  for (const type of requiredTypes) {
    const ids = (uploads[type] ?? []).map((attachment) => attachment.id);
    if (ids.length > 0) {
      attachmentIds[type] = ids;
    }
  }

  return Object.keys(attachmentIds).length > 0 ? attachmentIds : undefined;
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

    if (field.type === "boolean") {
      if (rawValue === "true") payload[field.name] = true;
      else if (rawValue === "false") payload[field.name] = false;
      continue;
    }
    if (rawValue === undefined || rawValue === "") continue;
    if (field.type === "number" || field.type === "money" || field.name === "author_employee_id") payload[field.name] = Number(rawValue);
    else payload[field.name] = rawValue;
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

function getErrorMessage(error: unknown, fallback: string): string {
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

  return fallback;
}
