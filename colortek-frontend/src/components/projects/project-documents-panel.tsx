"use client";

import { Badge } from "@/components/tailgrids/core/badge";
import { CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { getProjectDocuments } from "@/services/projectService";
import type { ProjectDetail } from "@/types/projects";
import { formatEnumLabel } from "@/utils/enum-label";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";

/**
 * Every file attached anywhere under the project — payment proofs, signed
 * sample approvals, site photos — gathered into one list, with the record each
 * one came from so it can be traced back.
 */
export default function ProjectDocumentsPanel({
  project,
}: {
  project: ProjectDetail;
}) {
  const t = useTranslations("projects");

  const query = useQuery({
    queryKey: ["projects", "documents", project.id],
    queryFn: () => getProjectDocuments(project.id),
  });

  const documents = query.data?.data ?? [];

  return (
    <div>
      <CardTitle className="text-base">{t("documents")}</CardTitle>

      {query.isLoading ? <Skeleton className="mt-3 h-24" /> : null}

      {query.isSuccess && documents.length === 0 ? (
        <CardDescription className="mt-2">{t("noDocuments")}</CardDescription>
      ) : null}

      {documents.length > 0 ? (
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[36rem] text-start text-sm">
            <thead>
              <tr className="border-b border-card-border text-text-secondary">
                <th className="py-2 pe-4 text-start font-medium">
                  {t("documentName")}
                </th>
                <th className="py-2 pe-4 text-start font-medium">
                  {t("documentType")}
                </th>
                <th className="py-2 pe-4 text-start font-medium">
                  {t("documentSource")}
                </th>
                <th className="py-2 text-start font-medium">
                  {t("documentUploadedBy")}
                </th>
              </tr>
            </thead>
            <tbody>
              {documents.map((document) => (
                <tr
                  key={document.id}
                  className="border-b border-card-border/60"
                >
                  <td className="py-2 pe-4 text-text-primary">
                    {document.original_name}
                    {document.size_bytes ? (
                      <span className="ms-2 text-xs text-text-tertiary">
                        {formatBytes(document.size_bytes)}
                      </span>
                    ) : null}
                  </td>
                  <td className="py-2 pe-4">
                    <Badge size="sm">{formatEnumLabel(document.type)}</Badge>
                  </td>
                  <td className="py-2 pe-4 text-text-secondary">
                    {formatEnumLabel(document.source_type)}
                    {document.source_id ? ` #${document.source_id}` : ""}
                  </td>
                  <td className="py-2 text-text-secondary">
                    {document.uploaded_by?.name ?? "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${Math.round(bytes / 1024)} KB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
