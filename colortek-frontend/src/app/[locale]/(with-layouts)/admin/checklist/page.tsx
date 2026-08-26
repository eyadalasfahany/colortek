"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import { Button } from "@/components/tailgrids/core/button";
import { Input } from "@/components/tailgrids/core/input";
import { TableBody, TableCell, TableHead, TableHeader, TableRoot, TableRow } from "@/components/tailgrids/core/table";
import { queryKeys } from "@/lib/queryKeys";
import { getChecklistItems, updateChecklistItem } from "@/services/admin/adminService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

export default function AdminChecklistPage() {
  const queryClient = useQueryClient();
  const [editingId, setEditingId] = useState<number | null>(null);
  const [labelEn, setLabelEn] = useState("");
  const [labelAr, setLabelAr] = useState("");

  const itemsQuery = useQuery({ queryKey: queryKeys.admin.checklist(), queryFn: getChecklistItems });

  const saveMutation = useMutation({
    mutationFn: ({ id, body }: { id: number; body: Record<string, unknown> }) => updateChecklistItem(id, body),
    onSuccess: () => {
      setEditingId(null);
      queryClient.invalidateQueries({ queryKey: queryKeys.admin.checklist() });
    },
  });

  const items = itemsQuery.data?.data ?? [];

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title="Site checklist items"
        description="Labels should match the paper site visit form wording (Arabic labels from the seed table)."
        trail={[
          { href: "/", label: "Home" },
          { href: "/admin/checklist", label: "Checklist" },
        ]}
      />

      <div className="px-2 lg:px-6">
        <TableRoot>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Label (EN)</TableHead>
              <TableHead>Label (AR)</TableHead>
              <TableHead>Critical</TableHead>
              <TableHead>Order</TableHead>
              <TableHead />
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.map((item) => (
              <TableRow key={item.id}>
                <TableCell>{item.code}</TableCell>
                <TableCell>
                  {editingId === item.id ? (
                    <Input value={labelEn} onChange={(e) => setLabelEn(e.target.value)} />
                  ) : (
                    item.label_en
                  )}
                </TableCell>
                <TableCell>
                  {editingId === item.id ? (
                    <Input value={labelAr} onChange={(e) => setLabelAr(e.target.value)} />
                  ) : (
                    item.label_ar
                  )}
                </TableCell>
                <TableCell>{item.is_readiness_critical ? "Yes" : "No"}</TableCell>
                <TableCell>{item.sort_order}</TableCell>
                <TableCell>
                  {editingId === item.id ? (
                    <Button
                      size="sm"
                      onClick={() =>
                        saveMutation.mutate({
                          id: item.id,
                          body: { label_en: labelEn, label_ar: labelAr },
                        })
                      }
                    >
                      Save
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => {
                        setEditingId(item.id);
                        setLabelEn(item.label_en);
                        setLabelAr(item.label_ar);
                      }}
                    >
                      Edit
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </TableRoot>
      </div>
    </div>
  );
}
