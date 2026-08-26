"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
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
import {
  getDepartmentOptions,
  getEmployeeOptions,
  getProjectOptions,
} from "@/services/optionsService";
import { getPeopleHours } from "@/services/peopleHoursService";
import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";

function defaultFrom(): string {
  const d = new Date();
  d.setDate(d.getDate() - 7);
  return d.toISOString().slice(0, 10);
}

function defaultTo(): string {
  return new Date().toISOString().slice(0, 10);
}

function HoursTable({
  title,
  hoursLabel,
  empty,
  rows,
}: {
  title: string;
  hoursLabel: string;
  empty: string;
  rows: Array<{ key: string | number; label: string; hours: number }>;
}) {
  return (
    <div className="mt-4">
      <h3 className="mb-2 text-sm font-medium text-text-primary">{title}</h3>
      <TableRoot>
        <TableHeader>
          <TableRow>
            <TableHead>{title}</TableHead>
            <TableHead className="text-end">{hoursLabel}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={2} className="py-6 text-center text-text-secondary">
                {empty}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow key={row.key}>
                <TableCell>{row.label}</TableCell>
                <TableCell className="text-end tabular-nums">{row.hours.toFixed(2)}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </TableRoot>
    </div>
  );
}

export default function PeopleHoursView() {
  const t = useTranslations("peopleHours");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const [from, setFrom] = useState(defaultFrom);
  const [to, setTo] = useState(defaultTo);
  const [projectId, setProjectId] = useState("");
  const [departmentId, setDepartmentId] = useState("");
  const [employeeId, setEmployeeId] = useState("");
  const [applied, setApplied] = useState({
    from: defaultFrom(),
    to: defaultTo(),
    project_id: undefined as number | undefined,
    department_id: undefined as number | undefined,
    employee_id: undefined as number | undefined,
  });

  const filters = useMemo(() => applied, [applied]);

  const projectsQuery = useQuery({
    queryKey: queryKeys.options.projects(),
    queryFn: getProjectOptions,
  });
  const departmentsQuery = useQuery({
    queryKey: queryKeys.options.departments(),
    queryFn: getDepartmentOptions,
  });
  const employeesQuery = useQuery({
    queryKey: queryKeys.options.employees(),
    queryFn: getEmployeeOptions,
  });

  const reportQuery = useQuery({
    queryKey: queryKeys.peopleHours.report(filters),
    queryFn: () => getPeopleHours(filters),
  });

  const workshop = reportQuery.data?.workshop;
  const site = reportQuery.data?.site;
  const workshopTitle =
    locale === "ar" ? (workshop?.label_ar ?? t("workshopTitle")) : (workshop?.label_en ?? t("workshopTitle"));
  const siteTitle =
    locale === "ar" ? (site?.label_ar ?? t("siteTitle")) : (site?.label_en ?? t("siteTitle"));

  return (
    <PermissionGate permission="time.view_all">
      <div className="px-4 pt-6 lg:px-6">
        <div className="mb-6">
          <h1 className="text-2xl font-semibold text-text-primary">{t("title")}</h1>
          <p className="mt-1 text-sm text-text-secondary">{t("description")}</p>
        </div>

        <Card className="mb-6">
          <CardTitle>{t("dateRange")}</CardTitle>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div className="flex flex-col gap-1.5">
              <Label>{t("from")}</Label>
              <Input
                type="date"
                value={from}
                onChange={(e) => setFrom(e.target.value)}
                className="px-3 py-2 text-sm"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label>{t("to")}</Label>
              <Input
                type="date"
                value={to}
                onChange={(e) => setTo(e.target.value)}
                className="px-3 py-2 text-sm"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label>{t("project")}</Label>
              <select
                value={projectId}
                onChange={(e) => setProjectId(e.target.value)}
                className="rounded-lg border border-card-border bg-card-bg px-3 py-2 text-sm"
              >
                <option value="">{tCommon("all")}</option>
                {(projectsQuery.data ?? []).map((opt) => (
                  <option key={opt.id} value={opt.id}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="flex flex-col gap-1.5">
              <Label>{t("department")}</Label>
              <select
                value={departmentId}
                onChange={(e) => setDepartmentId(e.target.value)}
                className="rounded-lg border border-card-border bg-card-bg px-3 py-2 text-sm"
              >
                <option value="">{tCommon("all")}</option>
                {(departmentsQuery.data ?? []).map((opt) => (
                  <option key={opt.id} value={opt.id}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="flex flex-col gap-1.5">
              <Label>{t("employee")}</Label>
              <select
                value={employeeId}
                onChange={(e) => setEmployeeId(e.target.value)}
                className="rounded-lg border border-card-border bg-card-bg px-3 py-2 text-sm"
              >
                <option value="">{tCommon("all")}</option>
                {(employeesQuery.data ?? []).map((opt) => (
                  <option key={opt.id} value={opt.id}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="mt-4">
            <Button
              variant="primary"
              appearance="fill"
              onPress={() =>
                setApplied({
                  from,
                  to,
                  project_id: projectId ? Number(projectId) : undefined,
                  department_id: departmentId ? Number(departmentId) : undefined,
                  employee_id: employeeId ? Number(employeeId) : undefined,
                })
              }
            >
              {t("applyFilters")}
            </Button>
          </div>
        </Card>

        {reportQuery.isError ? (
          <p className="mb-4 text-sm text-status-error">{t("loadError")}</p>
        ) : null}

        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardTitle>{workshopTitle}</CardTitle>
            <CardDescription className="mt-2">{t("workshopDescription")}</CardDescription>
            {reportQuery.isLoading ? (
              <p className="mt-4 text-sm text-text-secondary">{tCommon("loading")}</p>
            ) : (
              <>
                <HoursTable
                  title={t("byProject")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(workshop?.by_project ?? []).map((row) => ({
                    key: row.project_id,
                    label: `${row.reference} — ${row.name}`,
                    hours: row.hours,
                  }))}
                />
                <HoursTable
                  title={t("byDepartment")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(workshop?.by_department ?? []).map((row) => ({
                    key: row.department_id,
                    label: row.name || row.code,
                    hours: row.hours,
                  }))}
                />
                <HoursTable
                  title={t("byEmployee")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(workshop?.by_employee ?? []).map((row) => ({
                    key: row.employee_id,
                    label: row.name,
                    hours: row.hours,
                  }))}
                />
              </>
            )}
          </Card>

          <Card>
            <CardTitle>{siteTitle}</CardTitle>
            <CardDescription className="mt-2">{t("siteDescription")}</CardDescription>
            {reportQuery.isLoading ? (
              <p className="mt-4 text-sm text-text-secondary">{tCommon("loading")}</p>
            ) : (
              <>
                <HoursTable
                  title={t("byProject")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(site?.by_project ?? []).map((row) => ({
                    key: row.project_id,
                    label: `${row.reference} — ${row.name}`,
                    hours: row.hours,
                  }))}
                />
                <HoursTable
                  title={t("byDepartment")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(site?.by_department ?? []).map((row) => ({
                    key: row.department_id,
                    label: row.name || row.code,
                    hours: row.hours,
                  }))}
                />
                <HoursTable
                  title={t("byEmployee")}
                  hoursLabel={t("hours")}
                  empty={t("empty")}
                  rows={(site?.by_employee ?? []).map((row) => ({
                    key: row.employee_id,
                    label: row.name,
                    hours: row.hours,
                  }))}
                />
              </>
            )}
          </Card>
        </div>
      </div>
    </PermissionGate>
  );
}
