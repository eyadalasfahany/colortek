"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { TableBody, TableCell, TableHead, TableHeader, TableRoot, TableRow } from "@/components/tailgrids/core/table";
import { queryKeys } from "@/lib/queryKeys";
import {
  createWorkflowDraft,
  getWorkflowTemplates,
  publishWorkflowTemplate,
} from "@/services/admin/adminService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

export default function AdminWorkflowsPage() {
  const queryClient = useQueryClient();
  const templatesQuery = useQuery({
    queryKey: queryKeys.admin.workflows(),
    queryFn: getWorkflowTemplates,
  });

  const draftMutation = useMutation({
    mutationFn: createWorkflowDraft,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.workflows() }),
  });

  const publishMutation = useMutation({
    mutationFn: publishWorkflowTemplate,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.workflows() }),
  });

  const rows = templatesQuery.data?.data ?? [];

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title="Workflow templates"
        description="Read published versions and edit drafts. SLA values from seed are proposed — confirm with client."
        trail={[
          { href: "/", label: "Home" },
          { href: "/admin/workflows", label: "Workflows" },
        ]}
      />

      <div className="px-2 lg:px-6">
        <TableRoot>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Version</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((tpl) => (
              <TableRow key={tpl.id}>
                <TableCell>{tpl.code}</TableCell>
                <TableCell>v{tpl.version}</TableCell>
                <TableCell>
                  {tpl.is_draft ? <Badge color="gray">Draft</Badge> : null}
                  {tpl.is_active ? <Badge color="primary">Active</Badge> : null}
                </TableCell>
                <TableCell className="space-x-2">
                  {!tpl.is_draft && tpl.is_active ? (
                    <Button size="sm" variant="ghost" onClick={() => draftMutation.mutate(tpl.id)}>
                      Create draft
                    </Button>
                  ) : null}
                  {tpl.is_draft ? (
                    <Button
                      size="sm"
                      onClick={() => {
                        if (window.confirm("Publish this draft? New instances only.")) {
                          publishMutation.mutate(tpl.id);
                        }
                      }}
                    >
                      Publish
                    </Button>
                  ) : null}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </TableRoot>
      </div>
    </div>
  );
}
