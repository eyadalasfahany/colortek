"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import PermissionGate from "@/components/auth/permission-gate";
import { Alert } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Input } from "@/components/tailgrids/core/input";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import { getAdminSettings, patchAdminSettings } from "@/services/admin/adminService";
import type { AdminSetting } from "@/types/admin";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

function settingValue(settings: AdminSetting[], key: string): string {
  const row = settings.find((s) => s.key === key);
  if (!row) return "";
  if (typeof row.value === "boolean") return row.value ? "true" : "false";
  if (Array.isArray(row.value)) return row.value.join(",");
  if (row.value === null || row.value === undefined) return "";
  return String(row.value);
}

function AdminSettingsForm() {
  const t = useTranslations("admin");
  const tCommon = useTranslations("common");
  const queryClient = useQueryClient();
  const { can } = usePermissions();
  const canEdit = can("settings.manage");

  const settingsQuery = useQuery({
    queryKey: queryKeys.admin.settings(),
    queryFn: getAdminSettings,
    enabled: canEdit,
  });

  const [humidityMax, setHumidityMax] = useState("");
  const [sampleThreshold, setSampleThreshold] = useState("");
  const [blockAll, setBlockAll] = useState(false);
  const [defaultLocale, setDefaultLocale] = useState<"en" | "ar">("en");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!settingsQuery.data) return;
    setHumidityMax(settingValue(settingsQuery.data, "humidity_max"));
    setSampleThreshold(settingValue(settingsQuery.data, "sample_repeat_attempt_threshold"));
    setBlockAll(settingValue(settingsQuery.data, "block_all_when_site_not_ready") === "true");
    const locale = settingValue(settingsQuery.data, "default_locale");
    setDefaultLocale(locale === "ar" ? "ar" : "en");
  }, [settingsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const body: Record<string, unknown> = {
        block_all_when_site_not_ready: blockAll,
        default_locale: defaultLocale,
      };
      if (humidityMax.trim() === "") {
        body.humidity_max = null;
      } else {
        body.humidity_max = Number(humidityMax);
      }
      if (sampleThreshold.trim() !== "") {
        body.sample_repeat_attempt_threshold = Number(sampleThreshold);
      }
      await patchAdminSettings(body);
    },
    onSuccess: () => {
      setError(null);
      setMessage(t("settingsSaved"));
      queryClient.invalidateQueries({ queryKey: queryKeys.admin.settings() });
    },
    onError: (err: unknown) => {
      setMessage(null);
      setError(err instanceof Error ? err.message : t("settingsSaveError"));
    },
  });

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title={t("settingsTitle")}
        description={t("settingsDescription")}
        trail={[
          { href: "/", label: tCommon("home") },
          { href: "/admin/settings", label: t("settingsTitle") },
        ]}
      />

      <div className="space-y-5 px-2 lg:px-6">
        {error ? (
          <Alert status="error">{error}</Alert>
        ) : null}

        <section className="space-y-4 rounded-2xl border border-card-border bg-card-surface-area p-5">
          <h2 className="text-lg font-semibold text-text-primary">{t("companySettings")}</h2>
          <p className="text-sm text-text-secondary">{t("companySettingsHint")}</p>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="text-text-secondary">{t("humidityMax")}</span>
              <Input
                type="number"
                min={0}
                max={100}
                value={humidityMax}
                onChange={(e) => setHumidityMax(e.target.value)}
                placeholder={t("humidityMaxPlaceholder")}
              />
              <span className="block text-xs text-text-tertiary">{t("humidityMaxHint")}</span>
            </label>

            <label className="space-y-1 text-sm">
              <span className="text-text-secondary">{t("sampleRepeatThreshold")}</span>
              <Input
                type="number"
                min={1}
                value={sampleThreshold}
                onChange={(e) => setSampleThreshold(e.target.value)}
              />
              <span className="block text-xs text-text-tertiary">{t("sampleRepeatThresholdHint")}</span>
            </label>

            <label className="flex items-start gap-3 text-sm sm:col-span-2">
              <input
                type="checkbox"
                className="mt-1 size-4 rounded border-card-border"
                checked={blockAll}
                onChange={(e) => setBlockAll(e.target.checked)}
              />
              <span>
                <span className="block text-text-primary">{t("blockAllWhenSiteNotReady")}</span>
                <span className="block text-xs text-text-tertiary">{t("blockAllWhenSiteNotReadyHint")}</span>
              </span>
            </label>

            <label className="space-y-1 text-sm">
              <span className="text-text-secondary">{t("defaultLocale")}</span>
              <select
                value={defaultLocale}
                onChange={(e) => setDefaultLocale(e.target.value === "ar" ? "ar" : "en")}
                className="w-full rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm text-text-primary"
              >
                <option value="en">{tCommon("english")}</option>
                <option value="ar">{tCommon("arabic")}</option>
              </select>
            </label>
          </div>

          <Button onClick={() => saveMutation.mutate()} isDisabled={saveMutation.isPending || !canEdit}>
            {saveMutation.isPending ? tCommon("loading") : tCommon("save")}
          </Button>
          {message ? <p className="text-sm text-status-success">{message}</p> : null}
        </section>
      </div>
    </div>
  );
}

export default function AdminSettingsPage() {
  return (
    <PermissionGate permission="settings.manage">
      <AdminSettingsForm />
    </PermissionGate>
  );
}
