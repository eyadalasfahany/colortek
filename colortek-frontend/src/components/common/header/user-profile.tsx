"use client";

import { LogoutIcon } from "@/components/common/header/icons";
import { Avatar, AvatarFallback } from "@/components/tailgrids/core/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuHeader,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/tailgrids/core/dropdown";
import { useAuth } from "@/context/auth-context";
import { useRouter } from "@/i18n/navigation";
import { AltArrowDownIcon } from "@/utils/icon";
import { useTranslations } from "next-intl";
import { useTransition } from "react";

export function UserProfileButton() {
  const { user, logout } = useAuth();
  const t = useTranslations("common");
  const router = useRouter();
  const [isPending, startTransition] = useTransition();

  const displayName = user?.name ?? t("appName");
  const email = user?.email ?? "";
  const initials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");

  const handleLogout = () => {
    startTransition(async () => {
      await logout();
      router.replace("/login");
    });
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group flex items-center gap-2.5 rounded-lg border-0 p-0 transition-all outline-none focus-visible:ring-4 focus-visible:ring-input-primary-focus-border/20 focus-visible:ring-offset-1">
        <Avatar>
          <AvatarFallback className="size-10 rounded-lg border border-border-secondary-alt bg-background-gray-secondary_alt">
            {initials || "?"}
          </AvatarFallback>
        </Avatar>

        <span className="hidden text-sm leading-5 font-medium text-text-primary sm:inline">
          {displayName}
        </span>

        <AltArrowDownIcon className="text-icon-tertiary transition-transform duration-200 group-aria-expanded:-rotate-180" />
      </DropdownMenuTrigger>

      <DropdownMenuContent placement="bottom end" className="w-70 overflow-hidden p-0 shadow-3xl">
        <DropdownMenuHeader className="flex w-full items-center justify-start gap-2 border-b border-border-secondary-alt px-4 py-3">
          <Avatar size="md">
            <AvatarFallback className="border border-border-secondary-alt bg-background-gray-secondary_alt">
              {initials || "?"}
            </AvatarFallback>
          </Avatar>
          <span className="flex min-w-0 flex-col">
            <span className="truncate text-sm font-medium text-text-primary">{displayName}</span>
            {email ? <span className="truncate text-xs text-text-secondary">{email}</span> : null}
          </span>
        </DropdownMenuHeader>

        <DropdownMenuSeparator />

        <DropdownMenuItem
          onAction={handleLogout}
          isDisabled={isPending}
          className="m-1.5 w-auto cursor-pointer px-3 py-2.5"
        >
          <span className="text-icon-secondary group-hover:text-text-primary">
            <LogoutIcon />
          </span>
          <span className="leading-5">{t("logout")}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
