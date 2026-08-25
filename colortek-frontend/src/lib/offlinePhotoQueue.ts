import { uploadAttachment } from "@/services/attachmentService";

const queue: Array<{ id: string; file: File; type: string }> = [];

export function enqueuePhoto(file: File, type: string): void {
  queue.push({ id: crypto.randomUUID(), file, type });
}

export async function flushPhotoQueue(): Promise<number[]> {
  const ids: number[] = [];
  while (queue.length > 0) {
    const item = queue.shift();
    if (!item) break;
    ids.push((await uploadAttachment(item.file, item.type)).id);
  }
  return ids;
}

export function isOnline(): boolean {
  return typeof navigator === "undefined" ? true : navigator.onLine;
}
