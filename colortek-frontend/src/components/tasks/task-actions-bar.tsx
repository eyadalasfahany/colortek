"use client";

import { ApiError } from "@/config/axios";
import { Button } from "@/components/tailgrids/core/button";
import {
  Dialog,
  DialogBody,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/tailgrids/core/dialog";
import { Label } from "@/components/tailgrids/core/label";
import { TextArea } from "@/components/tailgrids/core/text-area";
import {
  addTaskComment,
  blockTask,
  reassignTask,
  pauseTask,
  releaseTask,
  resumeTask,
  unblockTask,
} from "@/services/taskService";
import type { TaskDetail } from "@/types/api";
import {
  getBlockerCategoryOptions,
  getUserOptions,
} from "@/services/optionsService";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useState } from "react";

interface TaskActionsBarProps {
  task: TaskDetail;
  isActionPending: boolean;
  onSuccess: () => void;
  onError: (message: string) => void;
  onCommentAdded: (comment: {
    id: string;
    body: string;
    created_at: string;
    author?: string;
  }) => void;
}

export function TaskActionsBar({
  task,
  isActionPending,
  onSuccess,
  onError,
  onCommentAdded,
}: TaskActionsBarProps) {
  const t = useTranslations("actions");
  const tTasks = useTranslations("tasks");
  const tCommon = useTranslations("common");
  const [blockOpen, setBlockOpen] = useState(false);
  const [unblockOpen, setUnblockOpen] = useState(false);
  const [reassignOpen, setReassignOpen] = useState(false);
  const [assigneeId, setAssigneeId] = useState("");
  const [commentOpen, setCommentOpen] = useState(false);
  const [blockReason, setBlockReason] = useState("");
  const [resolutionNote, setResolutionNote] = useState("");
  // Categories come from GET /options/blocker-categories — the previous
  // hardcoded list had labels that did not match the real rows, so every
  // choice recorded the wrong category.
  const blockerCategoriesQuery = useQuery({
    queryKey: queryKeys.options.blockerCategories(),
    queryFn: getBlockerCategoryOptions,
  });
  const blockerCategories = blockerCategoriesQuery.data ?? [];

  // Only people in the task's own department can sensibly pick it up.
  const { can } = usePermissions();
  const assigneesQuery = useQuery({
    queryKey: queryKeys.options.users(task.department?.id),
    queryFn: () => getUserOptions(task.department?.id),
    enabled: reassignOpen,
  });
  const [blockCategoryId, setBlockCategoryId] = useState("");
  const [commentBody, setCommentBody] = useState("");

  const mutationOptions = {
    onSuccess: () => {
      onSuccess();
      setBlockOpen(false);
      setUnblockOpen(false);
      setCommentOpen(false);
      setBlockReason("");
      setBlockCategoryId("");
      setResolutionNote("");
      setReassignOpen(false);
      setAssigneeId("");
      setCommentBody("");
    },
    onError: (error: unknown) => onError(getErrorMessage(error)),
  };

  const releaseMutation = useMutation({
    mutationFn: () => releaseTask(task.id),
    ...mutationOptions,
  });
  const pauseMutation = useMutation({
    mutationFn: () => pauseTask(task.id),
    ...mutationOptions,
  });
  const resumeMutation = useMutation({
    mutationFn: () => resumeTask(task.id),
    ...mutationOptions,
  });
  const unblockMutation = useMutation({
    mutationFn: () => unblockTask(task.id, resolutionNote),
    ...mutationOptions,
  });
  const reassignMutation = useMutation({
    mutationFn: () => reassignTask(task.id, Number(assigneeId)),
    ...mutationOptions,
  });
  const blockMutation = useMutation({
    mutationFn: () =>
      blockTask(task.id, {
        blocker_category_id: Number(blockCategoryId),
        reason: blockReason,
      }),
    ...mutationOptions,
  });
  const commentMutation = useMutation({
    mutationFn: () => addTaskComment(task.id, commentBody),
    onSuccess: () => {
      onCommentAdded({
        id: crypto.randomUUID(),
        body: commentBody,
        created_at: new Date().toISOString(),
      });
      setCommentOpen(false);
      setCommentBody("");
    },
    onError: (error: unknown) => onError(getErrorMessage(error)),
  });

  const pending =
    isActionPending ||
    releaseMutation.isPending ||
    pauseMutation.isPending ||
    resumeMutation.isPending ||
    unblockMutation.isPending ||
    blockMutation.isPending ||
    reassignMutation.isPending ||
    commentMutation.isPending;

  const secondaryActions = [];

  if (can("task.reassign")) {
    secondaryActions.push(
      <Button
        key="reassign"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setReassignOpen(true)}
      >
        {t("reassign")}
      </Button>,
    );
  }

  if (task.status === "claimed") {
    secondaryActions.push(
      <Button
        key="release"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => releaseMutation.mutate()}
      >
        {t("release")}
      </Button>,
      <Button
        key="block"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setBlockOpen(true)}
      >
        {t("block")}
      </Button>,
    );
  }

  if (task.status === "in_progress") {
    secondaryActions.push(
      <Button
        key="pause"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => pauseMutation.mutate()}
      >
        {t("pause")}
      </Button>,
      <Button
        key="block"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setBlockOpen(true)}
      >
        {t("block")}
      </Button>,
      <Button
        key="comment"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setCommentOpen(true)}
      >
        {t("comment")}
      </Button>,
    );
  }

  if (task.status === "paused") {
    secondaryActions.push(
      <Button
        key="block"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setBlockOpen(true)}
      >
        {t("block")}
      </Button>,
    );
  }

  if (task.status === "blocked") {
    secondaryActions.push(
      <Button
        key="comment"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setCommentOpen(true)}
      >
        {t("comment")}
      </Button>,
    );
  }

  if (["waiting", "pending"].includes(task.status)) {
    secondaryActions.push(
      <Button
        key="comment"
        variant="ghost"
        appearance="outline"
        isDisabled={pending}
        onPress={() => setCommentOpen(true)}
      >
        {t("comment")}
      </Button>,
    );
  }

  return (
    <>
      {secondaryActions.length > 0 ? (
        <div className="mb-4 flex flex-wrap gap-2">{secondaryActions}</div>
      ) : null}

      {task.status === "paused" ? (
        <div className="mb-4">
          <Button
            variant="primary"
            appearance="fill"
            isDisabled={pending}
            onPress={() => resumeMutation.mutate()}
          >
            {t("resume")}
          </Button>
        </div>
      ) : null}

      {task.status === "blocked" ? (
        <div className="mb-4">
          <Button
            variant="primary"
            appearance="fill"
            isDisabled={pending}
            onPress={() => setUnblockOpen(true)}
          >
            {t("unblock")}
          </Button>
        </div>
      ) : null}

      <Dialog isOpen={blockOpen} onOpenChange={setBlockOpen}>
        <DialogHeader>
          <DialogTitle>{t("block")}</DialogTitle>
          <DialogDescription>{tTasks("blockReason")}</DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3 py-0">
          <div>
            <Label htmlFor="block-category">{tTasks("blockCategory")}</Label>
            <select
              id="block-category"
              value={blockCategoryId}
              onChange={(e) => setBlockCategoryId(e.target.value)}
              className="mt-1 w-full rounded-lg border border-card-border px-3 py-2 text-sm"
            >
              <option value="">{tCommon("select")}</option>
              {blockerCategories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <Label htmlFor="block-reason">{tTasks("blockReason")}</Label>
            <TextArea
              id="block-reason"
              value={blockReason}
              onChange={(e) => setBlockReason(e.target.value)}
              className="mt-1 min-h-24"
            />
          </div>
          <Button
            variant="primary"
            appearance="fill"
            isDisabled={!blockReason.trim() || pending}
            onPress={() => blockMutation.mutate()}
          >
            {t("block")}
          </Button>
        </DialogBody>
      </Dialog>

      <Dialog isOpen={unblockOpen} onOpenChange={setUnblockOpen}>
        <DialogHeader>
          <DialogTitle>{t("unblock")}</DialogTitle>
          <DialogDescription>{tTasks("unblockDescription")}</DialogDescription>
        </DialogHeader>
        <DialogBody className="py-0">
          <Label htmlFor="unblock-note">{tTasks("unblockNote")}</Label>
          <TextArea
            id="unblock-note"
            value={resolutionNote}
            onChange={(e) => setResolutionNote(e.target.value)}
            className="mt-1 min-h-24"
          />
          <Button
            variant="primary"
            appearance="fill"
            className="mt-3"
            isDisabled={!resolutionNote.trim() || pending}
            onPress={() => unblockMutation.mutate()}
          >
            {t("unblock")}
          </Button>
        </DialogBody>
      </Dialog>

      <Dialog isOpen={reassignOpen} onOpenChange={setReassignOpen}>
        <DialogHeader>
          <DialogTitle>{t("reassign")}</DialogTitle>
          <DialogDescription>{tTasks("reassignDescription")}</DialogDescription>
        </DialogHeader>
        <DialogBody className="py-0">
          <Label htmlFor="assignee">{tTasks("assignee")}</Label>
          <select
            id="assignee"
            value={assigneeId}
            onChange={(e) => setAssigneeId(e.target.value)}
            className="mt-1 w-full rounded-lg border border-card-border px-3 py-2 text-sm"
          >
            <option value="">{tCommon("select")}</option>
            {(assigneesQuery.data ?? []).map((user) => (
              <option key={user.id} value={user.id}>
                {user.label}
              </option>
            ))}
          </select>
          <Button
            variant="primary"
            appearance="fill"
            className="mt-3"
            isDisabled={assigneeId === "" || pending}
            onPress={() => reassignMutation.mutate()}
          >
            {t("reassign")}
          </Button>
        </DialogBody>
      </Dialog>

      <Dialog isOpen={commentOpen} onOpenChange={setCommentOpen}>
        <DialogHeader>
          <DialogTitle>{t("comment")}</DialogTitle>
        </DialogHeader>
        <DialogBody className="py-0">
          <TextArea
            value={commentBody}
            onChange={(e) => setCommentBody(e.target.value)}
            className="min-h-24"
            placeholder={tTasks("commentPlaceholder")}
          />
          <Button
            variant="primary"
            appearance="fill"
            className="mt-3"
            isDisabled={!commentBody.trim() || pending}
            onPress={() => commentMutation.mutate()}
          >
            {tCommon("submit")}
          </Button>
        </DialogBody>
      </Dialog>
    </>
  );
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Something went wrong.";
}
