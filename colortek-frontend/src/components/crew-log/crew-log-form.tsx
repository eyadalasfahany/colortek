"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import { TextArea } from "@/components/tailgrids/core/text-area";
import { queryKeys } from "@/lib/queryKeys";
import { getProjects } from "@/services/projectService";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

interface CrewMember {
  employeeId: string;
  hours: string;
}

export default function CrewLogForm() {
  return (
    <PermissionGate permission="time.crew_log_submit">
      <CrewLogContent />
    </PermissionGate>
  );
}

function CrewLogContent() {
  const [projectId, setProjectId] = useState("");
  const [workDone, setWorkDone] = useState("");
  const [issues, setIssues] = useState("");
  const [members, setMembers] = useState<CrewMember[]>([{ employeeId: "", hours: "" }]);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  const projectsQuery = useQuery({
    queryKey: queryKeys.projects.list({ status: "active" }),
    queryFn: () => getProjects({ per_page: 50, status: "active" }),
  });

  function addMember() {
    setMembers((current) => [...current, { employeeId: "", hours: "" }]);
  }

  function updateMember(index: number, patch: Partial<CrewMember>) {
    setMembers((current) =>
      current.map((member, i) => (i === index ? { ...member, ...patch } : member)),
    );
  }

  function handleSaveDraft() {
    setSavedMessage("Draft saved locally. Submit when the crew log API is connected.");
  }

  return (
    <div className="mx-auto max-w-lg px-4 pt-6 pb-24 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Crew log</h1>
        <p className="mt-1 text-sm text-text-secondary">
          End-of-day site hours — mobile first.
        </p>
      </div>

      {savedMessage ? (
        <Alert status="success" className="mb-4">
          <AlertDescription>{savedMessage}</AlertDescription>
        </Alert>
      ) : null}

      <Card className="space-y-4">
        <div>
          <Label htmlFor="log-date">Date</Label>
          <Input
            id="log-date"
            type="date"
            defaultValue={new Date().toISOString().slice(0, 10)}
            className="mt-1.5 w-full px-3 py-3 text-base"
          />
        </div>

        <div>
          <Label htmlFor="project">Project</Label>
          <select
            id="project"
            value={projectId}
            onChange={(e) => setProjectId(e.target.value)}
            className="mt-1.5 w-full rounded-lg border border-card-border bg-card-bg px-3 py-3 text-base"
          >
            <option value="">Select project…</option>
            {(projectsQuery.data?.data ?? []).map((project) => (
              <option key={project.id} value={project.id}>
                {project.reference} · {project.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <CardTitle className="mb-2 text-base">Workers & hours</CardTitle>
          {members.map((member, index) => (
            <div key={index} className="mb-3 grid grid-cols-[1fr_100px] gap-2">
              <Input
                placeholder="Employee name or ID"
                value={member.employeeId}
                onChange={(e) => updateMember(index, { employeeId: e.target.value })}
                className="px-3 py-3 text-base"
              />
              <Input
                type="number"
                min="0"
                step="0.5"
                placeholder="Hrs"
                value={member.hours}
                onChange={(e) => updateMember(index, { hours: e.target.value })}
                className="px-3 py-3 text-base"
              />
            </div>
          ))}
          <Button variant="ghost" appearance="outline" onPress={addMember}>
            Add worker
          </Button>
        </div>

        <div>
          <Label htmlFor="work-done">Work done</Label>
          <TextArea
            id="work-done"
            value={workDone}
            onChange={(e) => setWorkDone(e.target.value)}
            className="mt-1.5 min-h-24 w-full text-base"
          />
        </div>

        <div>
          <Label htmlFor="issues">Issues</Label>
          <TextArea
            id="issues"
            value={issues}
            onChange={(e) => setIssues(e.target.value)}
            className="mt-1.5 min-h-20 w-full text-base"
          />
        </div>

        <div className="flex flex-col gap-2 sm:flex-row">
          <Button variant="primary" appearance="outline" size="lg" className="flex-1" onPress={handleSaveDraft}>
            Save draft
          </Button>
          <Button variant="primary" appearance="fill" size="lg" className="flex-1" isDisabled>
            Submit (API pending)
          </Button>
        </div>

        <CardDescription>
          A submitted log is locked. Site hours come from crew logs — not workshop timers.
        </CardDescription>
      </Card>
    </div>
  );
}
