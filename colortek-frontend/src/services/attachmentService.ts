import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";

export interface UploadedAttachment {
  id: number;
  type: string;
  filename: string;
  mime_type: string;
  size_bytes: number;
}

export async function uploadAttachment(
  file: File,
  type: string,
  caption?: string,
): Promise<UploadedAttachment> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("type", type);
  if (caption) {
    formData.append("caption", caption);
  }

  const response = await axiosInstance.post<{ data: UploadedAttachment }>(
    "/attachments",
    formData,
  );

  return unwrapData<UploadedAttachment>(response);
}
