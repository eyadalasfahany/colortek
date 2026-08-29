"use client";

import { ApiError } from "@/config/axios";
import { Button } from "@/components/tailgrids/core/button";
import {
  Dialog,
  DialogBody,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/tailgrids/core/dialog";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import { queryKeys } from "@/lib/queryKeys";
import { createClient, getClients } from "@/services/clientService";
import { createProject } from "@/services/projectService";
import { createQuotation, getQuotations } from "@/services/quotationService";
import { getUserOptions } from "@/services/optionsService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";

interface CreateProjectDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
}

const selectClass =
  "mt-1 w-full rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm text-text-primary";

/**
 * Sales creates a project from a client and a quotation. Both can be picked
 * from what already exists or created inline, so the whole flow is one dialog.
 * `specs/13-odoo-gateway-and-seed-data.md` §2: projects originate here, and the
 * quotation number is typed by Sales.
 */
export default function CreateProjectDialog({
  isOpen,
  onOpenChange,
}: CreateProjectDialogProps) {
  const t = useTranslations("projects");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const queryClient = useQueryClient();

  const [name, setName] = useState("");
  const [reference, setReference] = useState("");
  const [clientId, setClientId] = useState("");
  const [quotationId, setQuotationId] = useState("");
  const [salesUserId, setSalesUserId] = useState("");
  const [error, setError] = useState<string | null>(null);

  const [newClientMode, setNewClientMode] = useState(false);
  const [newClientName, setNewClientName] = useState("");
  const [newClientContact, setNewClientContact] = useState("");
  const [newClientPhone, setNewClientPhone] = useState("");

  const [newQuotationMode, setNewQuotationMode] = useState(false);
  const [newQuotationNumber, setNewQuotationNumber] = useState("");
  const [newQuotationValue, setNewQuotationValue] = useState("");

  const clientsQuery = useQuery({
    queryKey: queryKeys.clients.list({ per_page: 100 }),
    queryFn: () => getClients({ per_page: 100 }),
    enabled: isOpen,
  });

  const quotationsQuery = useQuery({
    queryKey: queryKeys.quotations.list({ client_id: clientId }),
    queryFn: () =>
      getQuotations({ client_id: Number(clientId), per_page: 100 }),
    enabled: isOpen && clientId !== "" && !newClientMode,
  });

  const usersQuery = useQuery({
    queryKey: queryKeys.options.users(),
    queryFn: () => getUserOptions(),
    enabled: isOpen,
  });

  function reset() {
    setName("");
    setReference("");
    setClientId("");
    setQuotationId("");
    setSalesUserId("");
    setError(null);
    setNewClientMode(false);
    setNewClientName("");
    setNewClientContact("");
    setNewClientPhone("");
    setNewQuotationMode(false);
    setNewQuotationNumber("");
    setNewQuotationValue("");
  }

  const mutation = useMutation({
    mutationFn: async () => {
      // A new client and quotation are created first so the project can
      // reference them; each step is a real API call, nothing is faked here.
      const resolvedClientId = newClientMode
        ? (
            await createClient({
              name: newClientName,
              contact_person: newClientContact || null,
              phone: newClientPhone || null,
            })
          ).id
        : Number(clientId);

      let resolvedQuotationId: number | null = quotationId
        ? Number(quotationId)
        : null;

      if (newQuotationMode && newQuotationNumber) {
        resolvedQuotationId = (
          await createQuotation({
            number: newQuotationNumber,
            client_id: resolvedClientId,
            total_value: newQuotationValue || 0,
          })
        ).id;
      }

      return createProject({
        name,
        client_id: resolvedClientId,
        reference: reference || undefined,
        quotation_id: resolvedQuotationId,
        sales_user_id: salesUserId ? Number(salesUserId) : null,
      });
    },
    onSuccess: (project) => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["clients"] });
      queryClient.invalidateQueries({ queryKey: ["quotations"] });
      onOpenChange(false);
      reset();
      router.push(`/projects/${project.reference}`);
    },
    onError: (err: unknown) => setError(getErrorMessage(err)),
  });

  const clientChosen = newClientMode
    ? newClientName.trim() !== ""
    : clientId !== "";
  const canSubmit = name.trim() !== "" && clientChosen && !mutation.isPending;

  return (
    <Dialog
      isOpen={isOpen}
      onOpenChange={(open) => {
        onOpenChange(open);
        if (!open) reset();
      }}
    >
      <DialogHeader>
        <DialogTitle>{t("createTitle")}</DialogTitle>
        <DialogDescription>{t("createDescription")}</DialogDescription>
      </DialogHeader>
      <DialogBody className="space-y-4 py-0">
        {error ? (
          <p className="rounded-lg bg-error-50 px-3 py-2 text-sm text-error-600">
            {error}
          </p>
        ) : null}

        <div>
          <Label htmlFor="project-name">{t("projectName")}</Label>
          <Input
            id="project-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="mt-1 w-full px-3 py-2.5 text-sm"
          />
        </div>

        <div>
          <Label htmlFor="project-reference">{t("reference")}</Label>
          <Input
            id="project-reference"
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            placeholder={t("referencePlaceholder")}
            className="mt-1 w-full px-3 py-2.5 text-sm"
          />
        </div>

        <div>
          <div className="flex items-center justify-between">
            <Label htmlFor="project-client">{t("client")}</Label>
            <button
              type="button"
              className="text-xs text-brand-500 hover:text-brand-600"
              onClick={() => {
                setNewClientMode((v) => !v);
                setClientId("");
                setQuotationId("");
              }}
            >
              {newClientMode ? t("pickExistingClient") : t("addNewClient")}
            </button>
          </div>

          {newClientMode ? (
            <div className="mt-1 space-y-2">
              <Input
                value={newClientName}
                onChange={(e) => setNewClientName(e.target.value)}
                placeholder={t("clientName")}
                className="w-full px-3 py-2.5 text-sm"
              />
              <Input
                value={newClientContact}
                onChange={(e) => setNewClientContact(e.target.value)}
                placeholder={t("contactPerson")}
                className="w-full px-3 py-2.5 text-sm"
              />
              <Input
                value={newClientPhone}
                onChange={(e) => setNewClientPhone(e.target.value)}
                placeholder={t("phone")}
                className="w-full px-3 py-2.5 text-sm"
              />
            </div>
          ) : (
            <select
              id="project-client"
              value={clientId}
              onChange={(e) => {
                setClientId(e.target.value);
                setQuotationId("");
              }}
              className={selectClass}
            >
              <option value="">{tCommon("select")}</option>
              {(clientsQuery.data?.data ?? []).map((client) => (
                <option key={client.id} value={client.id}>
                  {client.name}
                </option>
              ))}
            </select>
          )}
        </div>

        <div>
          <div className="flex items-center justify-between">
            <Label htmlFor="project-quotation">{t("quotation")}</Label>
            <button
              type="button"
              className="text-xs text-brand-500 hover:text-brand-600"
              onClick={() => {
                setNewQuotationMode((v) => !v);
                setQuotationId("");
              }}
            >
              {newQuotationMode
                ? t("pickExistingQuotation")
                : t("addNewQuotation")}
            </button>
          </div>

          {newQuotationMode ? (
            <div className="mt-1 space-y-2">
              <Input
                value={newQuotationNumber}
                onChange={(e) => setNewQuotationNumber(e.target.value)}
                placeholder={t("quotationNumberPlaceholder")}
                className="w-full px-3 py-2.5 text-sm"
              />
              <Input
                value={newQuotationValue}
                onChange={(e) => setNewQuotationValue(e.target.value)}
                placeholder={t("quotationValue")}
                inputMode="decimal"
                className="w-full px-3 py-2.5 text-sm"
              />
            </div>
          ) : (
            <select
              id="project-quotation"
              value={quotationId}
              onChange={(e) => setQuotationId(e.target.value)}
              disabled={clientId === "" || newClientMode}
              className={selectClass}
            >
              <option value="">{tCommon("select")}</option>
              {(quotationsQuery.data?.data ?? []).map((quotation) => (
                <option key={quotation.id} value={quotation.id}>
                  {quotation.number} · {quotation.total_value}{" "}
                  {quotation.currency}
                </option>
              ))}
            </select>
          )}
        </div>

        <div>
          <Label htmlFor="project-sales">{t("salesUser")}</Label>
          <select
            id="project-sales"
            value={salesUserId}
            onChange={(e) => setSalesUserId(e.target.value)}
            className={selectClass}
          >
            <option value="">{t("salesUserDefault")}</option>
            {(usersQuery.data ?? []).map((user) => (
              <option key={user.id} value={user.id}>
                {user.label}
              </option>
            ))}
          </select>
        </div>

        <Button
          variant="primary"
          appearance="fill"
          className="w-full"
          isDisabled={!canSubmit}
          onPress={() => {
            setError(null);
            mutation.mutate();
          }}
        >
          {mutation.isPending ? tCommon("saving") : t("createTitle")}
        </Button>
      </DialogBody>
    </Dialog>
  );
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = error.errors
      ? Object.values(error.errors)[0]?.[0]
      : undefined;
    return first ?? error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Something went wrong.";
}
