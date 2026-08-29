"use client";

import { Badge } from "@/components/tailgrids/core/badge";
import {
  getProjectPayments,
  getProjectSamples,
  getProjectSiteVisits,
  getProjectTasks,
} from "@/services/projectService";
import type { ProjectDetail } from "@/types/projects";
import { Link } from "@/i18n/navigation";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { formatEnumLabel } from "@/utils/enum-label";

interface ProjectStageContextProps {
  project: ProjectDetail;
  /** The stage key the strip currently marks active. */
  stageKey: string;
}

/**
 * Shows the record that the active stage is actually about — the quotation on
 * the quotation stage, the payments on the payment stage, and so on — so the
 * workflow strip is not just a set of labels.
 */
export default function ProjectStageContext({
  project,
  stageKey,
}: ProjectStageContextProps) {
  switch (stageKey) {
    case "quotation":
    case "lead":
      return <QuotationContext project={project} />;
    case "payment":
      return <PaymentContext project={project} />;
    case "sample":
      return <SampleContext project={project} />;
    case "site":
      return <SiteContext project={project} />;
    default:
      return <TaskContext project={project} />;
  }
}

function Empty({ text }: { text: string }) {
  return <p className="text-sm text-text-tertiary">{text}</p>;
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-baseline justify-between gap-4 py-1">
      <span className="text-sm text-text-secondary">{label}</span>
      <span className="text-sm font-medium text-text-primary">{value}</span>
    </div>
  );
}

function QuotationContext({ project }: { project: ProjectDetail }) {
  const t = useTranslations("projects");
  const quotation = project.quotation;

  if (!quotation) {
    return <Empty text={t("noQuotation")} />;
  }

  return (
    <div>
      <Row label={t("quotationNumber")} value={quotation.number} />
      <Row
        label={t("quotationValue")}
        value={`${quotation.total_value} ${quotation.currency}`}
      />
      <Row
        label={t("quotationStatusLabel")}
        value={
          <Badge color={quotation.locked_at ? "success" : "primary"} size="sm">
            {formatEnumLabel(quotation.status)}
          </Badge>
        }
      />
    </div>
  );
}

function PaymentContext({ project }: { project: ProjectDetail }) {
  const t = useTranslations("projects");
  const query = useQuery({
    queryKey: ["projects", "payments", project.id],
    queryFn: () => getProjectPayments(project.id),
  });

  const payments = (Array.isArray(query.data) ? query.data : []) as Array<
    Record<string, unknown>
  >;

  if (payments.length === 0) {
    return <Empty text={t("noPayments")} />;
  }

  return (
    <ul className="space-y-1">
      {payments.map((payment) => (
        <li
          key={String(payment.id)}
          className="flex justify-between gap-4 text-sm"
        >
          <span className="text-text-secondary">
            {t("installment")} {String(payment.installment_number ?? "—")}
          </span>
          <span className="text-text-primary">
            {String(payment.amount ?? "—")} {String(payment.currency ?? "")}
          </span>
          <Badge size="sm">
            {formatEnumLabel(String(payment.status ?? ""))}
          </Badge>
        </li>
      ))}
    </ul>
  );
}

function SampleContext({ project }: { project: ProjectDetail }) {
  const t = useTranslations("projects");
  const query = useQuery({
    queryKey: ["projects", "samples", project.id],
    queryFn: () => getProjectSamples(project.id),
  });

  const samples = (query.data?.data ?? []) as Array<Record<string, unknown>>;

  if (samples.length === 0) {
    return <Empty text={t("noSamples")} />;
  }

  return (
    <ul className="space-y-1">
      {samples.map((sample) => (
        <li
          key={String(sample.id)}
          className="flex justify-between gap-4 text-sm"
        >
          <Link
            href={`/samples/${String(sample.reference)}`}
            className="text-brand-500 hover:text-brand-600"
          >
            {String(sample.reference ?? "—")}
          </Link>
          <span className="text-text-secondary">
            {String(sample.color ?? "")}
          </span>
          <Badge size="sm">
            {formatEnumLabel(String(sample.status ?? ""))}
          </Badge>
        </li>
      ))}
    </ul>
  );
}

function SiteContext({ project }: { project: ProjectDetail }) {
  const t = useTranslations("projects");
  const query = useQuery({
    queryKey: ["projects", "site-visits", project.id],
    queryFn: () => getProjectSiteVisits(project.id),
  });

  const visits = (query.data?.data ?? []) as Array<Record<string, unknown>>;

  if (visits.length === 0) {
    return <Empty text={t("noSiteVisits")} />;
  }

  return (
    <ul className="space-y-1">
      {visits.map((visit) => (
        <li
          key={String(visit.id)}
          className="flex justify-between gap-4 text-sm"
        >
          <span className="text-text-primary">
            {String(visit.reference ?? "—")}
          </span>
          <span className="text-text-secondary">
            {String(visit.visited_on ?? "")}
          </span>
          <Badge
            color={visit.readiness === "ready" ? "success" : "warning"}
            size="sm"
          >
            {formatEnumLabel(String(visit.readiness ?? ""))}
          </Badge>
        </li>
      ))}
    </ul>
  );
}

function TaskContext({ project }: { project: ProjectDetail }) {
  const t = useTranslations("projects");
  const query = useQuery({
    queryKey: ["projects", "stage-tasks", project.id],
    queryFn: () => getProjectTasks(project.id),
  });

  const open = query.data?.data ?? [];

  if (open.length === 0) {
    return <Empty text={t("noOpenTasks")} />;
  }

  return (
    <ul className="space-y-1">
      {open.map((task) => (
        <li key={task.id} className="flex justify-between gap-4 text-sm">
          <Link
            href={`/tasks/${task.id}`}
            className="text-brand-500 hover:text-brand-600"
          >
            {task.title}
          </Link>
          <Badge size="sm">{formatEnumLabel(task.status)}</Badge>
        </li>
      ))}
    </ul>
  );
}
