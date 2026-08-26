"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import { Alert } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Input } from "@/components/tailgrids/core/input";
import { TableBody, TableCell, TableHead, TableHeader, TableRoot, TableRow } from "@/components/tailgrids/core/table";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import {
  createHoliday,
  deleteHoliday,
  getAdminSettings,
  getHolidays,
  patchAdminSettings,
  postCalendarImpact,
} from "@/services/admin/adminService";
import type { AdminHoliday, AdminSetting } from "@/types/admin";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

function settingValue(settings: AdminSetting[], key: string): string {
  const row = settings.find((s) => s.key === key);
  if (!row) return "";
  if (Array.isArray(row.value)) return row.value.join(",");
  return String(row.value ?? "");
}

export default function AdminCalendarPage() {
  const { can, canAny } = usePermissions();
  const queryClient = useQueryClient();
  const canEditSettings = can("settings.manage");
  const canEditHolidays = can("holiday.manage");

  const settingsQuery = useQuery({
    queryKey: queryKeys.admin.settings(),
    queryFn: getAdminSettings,
    enabled: canEditSettings,
  });

  const holidaysQuery = useQuery({
    queryKey: queryKeys.admin.holidays(),
    queryFn: getHolidays,
    enabled: canAny("settings.manage", "holiday.manage"),
  });

  const [workStart, setWorkStart] = useState("");
  const [workEnd, setWorkEnd] = useState("");
  const [weekendDays, setWeekendDays] = useState("friday");
  const [holidayDate, setHolidayDate] = useState("");
  const [holidayNameEn, setHolidayNameEn] = useState("");
  const [holidayNameAr, setHolidayNameAr] = useState("");
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (settingsQuery.data) {
      setWorkStart(settingValue(settingsQuery.data, "work_start"));
      setWorkEnd(settingValue(settingsQuery.data, "work_end"));
      setWeekendDays(settingValue(settingsQuery.data, "weekend_days"));
    }
  }, [settingsQuery.data]);

  const saveSettings = useMutation({
    mutationFn: async () => {
      const impact = await postCalendarImpact({
        settings: {
          work_start: workStart,
          work_end: workEnd,
          weekend_days: weekendDays.split(",").map((d) => d.trim()).filter(Boolean),
        },
      });
      const count = impact.affected_task_count;
      if (count > 0 && !window.confirm(`${count} open task deadlines will change. Continue?`)) {
        throw new Error("Cancelled");
      }
      await patchAdminSettings({
        work_start: workStart,
        work_end: workEnd,
        weekend_days: weekendDays.split(",").map((d) => d.trim()).filter(Boolean),
        confirm: true,
      });
    },
    onSuccess: () => {
      setMessage("Calendar settings saved.");
      queryClient.invalidateQueries({ queryKey: queryKeys.admin.settings() });
    },
  });

  const addHoliday = useMutation({
    mutationFn: async () => {
      const impact = await postCalendarImpact({
        holiday: { date: holidayDate, type: "public", is_recurring: false },
      });
      if (impact.affected_task_count > 0 && !window.confirm(`${impact.affected_task_count} tasks affected. Continue?`)) {
        throw new Error("Cancelled");
      }
      await createHoliday({
        date: holidayDate,
        name: { en: holidayNameEn, ar: holidayNameAr || holidayNameEn },
        type: "public",
        is_recurring: false,
        confirm: true,
      });
    },
    onSuccess: () => {
      setHolidayDate("");
      setHolidayNameEn("");
      setHolidayNameAr("");
      queryClient.invalidateQueries({ queryKey: queryKeys.admin.holidays() });
    },
  });

  const removeHoliday = useMutation({
    mutationFn: (id: number) => deleteHoliday(id, true),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.holidays() }),
  });

  const holidays: AdminHoliday[] = holidaysQuery.data?.data ?? [];

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title="Calendar & holidays"
        description="Working hours and company holidays. Changes recalculate open task deadlines (overridden deadlines are kept)."
        trail={[
          { href: "/", label: "Home" },
          { href: "/admin/calendar", label: "Calendar" },
        ]}
      />

      <div className="space-y-5 px-2 lg:px-6">
        <Alert status="warning">
          Changing the company calendar affects every open deadline that is not manually overridden.
        </Alert>

        {canEditSettings ? (
          <section className="rounded-2xl border border-card-border bg-card-surface-area p-5 space-y-4">
            <h2 className="text-lg font-semibold text-text-primary">Working hours</h2>
            <p className="text-sm text-text-secondary">Timezone: Africa/Cairo · One working day = 8 hours</p>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="space-y-1 text-sm">
                <span className="text-text-secondary">Shift start</span>
                <Input value={workStart} onChange={(e) => setWorkStart(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-text-secondary">Shift end</span>
                <Input value={workEnd} onChange={(e) => setWorkEnd(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-text-secondary">Weekend days (comma-separated)</span>
                <Input value={weekendDays} onChange={(e) => setWeekendDays(e.target.value)} />
              </label>
            </div>
            <Button onClick={() => saveSettings.mutate()} isDisabled={saveSettings.isPending}>
              Save working hours
            </Button>
            {message ? <p className="text-sm text-status-success">{message}</p> : null}
          </section>
        ) : null}

        {canEditHolidays ? (
          <section className="rounded-2xl border border-card-border bg-card-surface-area p-5 space-y-4">
            <h2 className="text-lg font-semibold text-text-primary">Holidays</h2>
            <p className="text-sm text-text-secondary">Mark recurring Islamic holidays when the date shifts each year.</p>
            <div className="grid gap-4 sm:grid-cols-3">
              <Input type="date" value={holidayDate} onChange={(e) => setHolidayDate(e.target.value)} />
              <Input placeholder="Name (English)" value={holidayNameEn} onChange={(e) => setHolidayNameEn(e.target.value)} />
              <Input placeholder="Name (Arabic)" value={holidayNameAr} onChange={(e) => setHolidayNameAr(e.target.value)} />
            </div>
            <Button onClick={() => addHoliday.mutate()} isDisabled={!holidayDate || !holidayNameEn || addHoliday.isPending}>
              Add holiday
            </Button>

            <TableRoot>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {holidays.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="py-8 text-center text-text-secondary">
                      No holidays added yet. Add the first company or public holiday above.
                    </TableCell>
                  </TableRow>
                ) : (
                  holidays.map((h) => (
                    <TableRow key={h.id}>
                      <TableCell>{h.date}</TableCell>
                      <TableCell>{h.name.en}</TableCell>
                      <TableCell>{h.type}</TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm" onClick={() => removeHoliday.mutate(h.id)}>
                          Delete
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </TableRoot>
          </section>
        ) : null}
      </div>
    </div>
  );
}
