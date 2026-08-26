"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import PermissionGate from "@/components/auth/permission-gate";
import { Button } from "@/components/tailgrids/core/button";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import {
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRoot,
  TableRow,
} from "@/components/tailgrids/core/table";
import { queryKeys } from "@/lib/queryKeys";
import { getAuditLogs } from "@/services/auditService";
import { getUserOptions } from "@/services/optionsService";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";

function shortType(type: string): string {
  const parts = type.split("\\");
  return parts[parts.length - 1] || type;
}

export default function AuditLogView() {
  const t = useTranslations("admin");
  const tCommon = useTranslations("common");
  const [event, setEvent] = useState("");
  const [userId, setUserId] = useState("");
  const [auditableType, setAuditableType] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [applied, setApplied] = useState({
    event: "",
    user_id: undefined as number | undefined,
    auditable_type: "",
    since: "",
    until: "",
  });

  const filters = useMemo(
    () => ({
      event: applied.event || undefined,
      user_id: applied.user_id,
      auditable_type: applied.auditable_type || undefined,
      since: applied.since || undefined,
      until: applied.until || undefined,
      per_page: 50,
    }),
    [applied],
  );

  const usersQuery = useQuery({
    queryKey: queryKeys.options.users(),
    queryFn: getUserOptions,
  });

  const auditQuery = useQuery({
    queryKey: queryKeys.admin.audit(filters),
    queryFn: () => getAuditLogs(filters),
  });

  const rows = auditQuery.data?.data ?? [];

  return (
    <PermissionGate permission="audit.view">
      <div className="space-y-6 pb-8">
        <AdminPageHeader
          title={t("auditTitle")}
          description={t("auditDescription")}
          trail={[
            { href: "/", label: tCommon("home") },
            { href: "/admin/audit", label: t("auditTitle") },
          ]}
        />

        <div className="grid gap-3 px-2 sm:grid-cols-2 lg:grid-cols-5 lg:px-6">
          <div className="flex flex-col gap-1.5">
            <Label>{t("filterEvent")}</Label>
            <Input
              value={event}
              onChange={(e) => setEvent(e.target.value)}
              className="px-3 py-2 text-sm"
              placeholder="created"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t("filterUser")}</Label>
            <select
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              className="rounded-lg border border-card-border bg-card-bg px-3 py-2 text-sm"
            >
              <option value="">{tCommon("all")}</option>
              {(usersQuery.data ?? []).map((user) => (
                <option key={user.id} value={user.id}>
                  {user.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t("filterAuditableType")}</Label>
            <Input
              value={auditableType}
              onChange={(e) => setAuditableType(e.target.value)}
              className="px-3 py-2 text-sm"
              placeholder="App\\Models\\Project"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t("filterDateFrom")}</Label>
            <Input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className="px-3 py-2 text-sm"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t("filterDateTo")}</Label>
            <Input
              type="date"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className="px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div className="flex gap-2 px-2 lg:px-6">
          <Button
            variant="primary"
            appearance="fill"
            onPress={() =>
              setApplied({
                event,
                user_id: userId ? Number(userId) : undefined,
                auditable_type: auditableType,
                since: from,
                until: to,
              })
            }
          >
            {tCommon("apply")}
          </Button>
          <Button
            variant="primary"
            appearance="outline"
            onPress={() => {
              setEvent("");
              setUserId("");
              setAuditableType("");
              setFrom("");
              setTo("");
              setApplied({
                event: "",
                user_id: undefined,
                auditable_type: "",
                since: "",
                until: "",
              });
            }}
          >
            {tCommon("clearFilters")}
          </Button>
        </div>

        <div className="px-2 lg:px-6">
          {auditQuery.isError ? (
            <p className="py-8 text-center text-sm text-status-error">{t("auditLoadError")}</p>
          ) : (
            <TableRoot>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("columnWhen")}</TableHead>
                  <TableHead>{t("columnEvent")}</TableHead>
                  <TableHead>{t("columnUser")}</TableHead>
                  <TableHead>{t("columnAuditable")}</TableHead>
                  <TableHead>{t("columnReason")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {auditQuery.isLoading ? (
                  <TableRow>
                    <TableCell colSpan={5} className="py-8 text-center text-text-secondary">
                      {tCommon("loading")}
                    </TableCell>
                  </TableRow>
                ) : rows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="py-8 text-center text-text-secondary">
                      {t("auditEmpty")}
                    </TableCell>
                  </TableRow>
                ) : (
                  rows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="whitespace-nowrap text-sm">
                        {row.created_at
                          ? new Date(row.created_at).toLocaleString()
                          : tCommon("emptyDash")}
                      </TableCell>
                      <TableCell className="text-sm font-medium">{row.event}</TableCell>
                      <TableCell className="text-sm">
                        {row.user?.name ?? tCommon("emptyDash")}
                      </TableCell>
                      <TableCell className="text-sm">
                        {shortType(row.auditable_type)} #{row.auditable_id}
                      </TableCell>
                      <TableCell className="max-w-xs truncate text-sm text-text-secondary">
                        {row.reason ?? tCommon("emptyDash")}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </TableRoot>
          )}
        </div>
      </div>
    </PermissionGate>
  );
}
