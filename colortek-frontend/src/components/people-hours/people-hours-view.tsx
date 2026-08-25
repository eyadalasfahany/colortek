"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";

export default function PeopleHoursView() {
  return (
    <PermissionGate permission="time.view_all">
      <div className="px-4 pt-6 lg:px-6" dir="auto">
        <div className="mb-6">
          <h1 className="text-2xl font-semibold text-text-primary">People & Hours</h1>
          <p className="mt-1 text-sm text-text-secondary">
            Workshop hours from timers; site hours from crew logs — never merged without a label.
          </p>
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardTitle>Workshop — live timers</CardTitle>
            <CardDescription className="mt-2">
              Hours from active task timers. Aggregated people-hours API is pending; use the
              workshop dashboard for who is working now.
            </CardDescription>
          </Card>
          <Card>
            <CardTitle>Site — crew logs</CardTitle>
            <CardDescription className="mt-2">
              End-of-day figures from submitted crew logs. These are not live counts.
            </CardDescription>
          </Card>
        </div>

        <Card className="mt-4">
          <CardTitle>Date range report</CardTitle>
          <CardDescription className="mt-2">
            Full cross-project hours reporting will connect to{" "}
            <code className="text-xs">GET /time-entries</code> when the API ships. Filter by
            project, department, and employee per spec §17.
          </CardDescription>
        </Card>
      </div>
    </PermissionGate>
  );
}
