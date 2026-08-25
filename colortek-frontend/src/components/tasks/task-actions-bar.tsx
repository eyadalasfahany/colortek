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
  pauseTask,
  releaseTask,
  resumeTask,
  unblockTask,
} from "@/services/taskService";
import type { TaskDetail } from "@/types/api";
import { useMutation } from "@tanstack/react-query";
import { useState } from "react";

interface TaskActionsBarProps {
  task: TaskDetail;
  isActionPending: boolean;
  onSuccess: () => void;
  onError: (message: string) => void;
  onCommentAdded: (comment: { id: string; body: string; created_at: string; author?: string }) => void;
}

export function TaskActionsBar({
  task,
  isActionPending,
  onSuccess,
  onError,
  onCommentAdded,
}: TaskActionsBarProps) {
  const [blockOpen, setBlockOpen] = useState(false);
  const [commentOpen, setCommentOpen] = useState(false);
  const [blockReason, setBlockReason] = useState("");
  const [blockCategoryId, setBlockCategoryId] = useState("1");
  const [commentBody, setCommentBody] = useState("");

  const mutationOptions = {
    onSuccess: () => {
      onSuccess();
      setBlockOpen(false);
      setCommentOpen(false);
      setBlockReason("");
      setCommentBody("");
    },
    onError: (error: unknown) => onError(getErrorMessage(error)),
  };

  const releaseMutation = useMutation({ mutationFn: () => releaseTask(task.id), ...mutationOptions });
  const pauseMutation = useMutation({ mutationFn: () => pauseTask(task.id), ...mutationOptions });
  const resumeMutation = useMutation({ mutationFn: () => resumeTask(task.id), ...mutationOptions });
  const unblockMutation = useMutation({ mutationFn: () => unblockTask(task.id), ...mutationOptions });
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
    commentMutation.isPending;

  const secondaryActions = [];

  if (task.status === "claimed") {
    secondaryActions.push(
      <Button key="release" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => releaseMutation.mutate()}>
        Release
      </Button>,
      <Button key="block" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setBlockOpen(true)}>
        Block
      </Button>,
    );
  }

  if (task.status === "in_progress") {
    secondaryActions.push(
      <Button key="pause" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => pauseMutation.mutate()}>
        Pause
      </Button>,
      <Button key="block" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setBlockOpen(true)}>
        Block
      </Button>,
      <Button key="comment" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setCommentOpen(true)}>
        Add comment
      </Button>,
    );
  }

  if (task.status === "paused") {
    secondaryActions.push(
      <Button key="block" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setBlockOpen(true)}>
        Block
      </Button>,
    );
  }

  if (task.status === "blocked") {
    secondaryActions.push(
      <Button key="comment" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setCommentOpen(true)}>
        Add comment
      </Button>,
    );
  }

  if (["waiting", "pending"].includes(task.status)) {
    secondaryActions.push(
      <Button key="comment" variant="ghost" appearance="outline" isDisabled={pending} onPress={() => setCommentOpen(true)}>
        Add comment
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
          <Button variant="primary" appearance="fill" isDisabled={pending} onPress={() => resumeMutation.mutate()}>
            Resume
          </Button>
        </div>
      ) : null}

      {task.status === "blocked" ? (
        <div className="mb-4">
          <Button variant="primary" appearance="fill" isDisabled={pending} onPress={() => unblockMutation.mutate()}>
            Unblock
          </Button>
        </div>
      ) : null}

      <Dialog isOpen={blockOpen} onOpenChange={setBlockOpen}>
        <DialogHeader>
          <DialogTitle>Block task</DialogTitle>
          <DialogDescription>Describe what is wrong so the right team is notified.</DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3 py-0">
            <div>
              <Label htmlFor="block-category">Category</Label>
              <select
                id="block-category"
                value={blockCategoryId}
                onChange={(e) => setBlockCategoryId(e.target.value)}
                className="mt-1 w-full rounded-lg border border-card-border px-3 py-2 text-sm"
              >
                <option value="1">Missing material</option>
                <option value="2">Waiting on client</option>
                <option value="3">Equipment issue</option>
                <option value="4">Other</option>
              </select>
            </div>
            <div>
              <Label htmlFor="block-reason">What is wrong *</Label>
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
              Block task
            </Button>
        </DialogBody>
      </Dialog>

      <Dialog isOpen={commentOpen} onOpenChange={setCommentOpen}>
        <DialogHeader>
          <DialogTitle>Add comment</DialogTitle>
        </DialogHeader>
        <DialogBody className="py-0">
          <TextArea value={commentBody} onChange={(e) => setCommentBody(e.target.value)} className="min-h-24" />
          <Button
            variant="primary"
            appearance="fill"
            className="mt-3"
            isDisabled={!commentBody.trim() || pending}
            onPress={() => commentMutation.mutate()}
          >
            Post comment
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
