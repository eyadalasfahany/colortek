import { BoxIcon, EyeIcon, MoneyBagIcon, UserGroupIcon } from "./icons";
import type { OverviewStatKey } from "@/services/api/home";

import type { OverviewStatDisplayConfig } from "./types";

export const overviewStatDisplayConfig: Record<OverviewStatKey, OverviewStatDisplayConfig> = {
  views: {
    title: "Total Views",
    icon: <EyeIcon />,
    iconBgClass: "bg-[color-mix(in_srgb,var(--color-info-500)_10%,transparent)]",
    iconColorClass: "text-[var(--color-info-500)]",
  },
  profit: {
    title: "Total profit",
    icon: <MoneyBagIcon />,
    iconBgClass: "bg-[color-mix(in_srgb,var(--color-green-600)_10%,transparent)]",
    iconColorClass: "text-[var(--color-green-600)]",
  },
  orders: {
    title: "Total Order",
    icon: <BoxIcon />,
    iconBgClass: "bg-[color-mix(in_srgb,var(--color-orange-500)_10%,transparent)]",
    iconColorClass: "text-[var(--color-orange-500)]",
  },
  users: {
    title: "Total User",
    icon: <UserGroupIcon />,
    iconBgClass: "bg-[color-mix(in_srgb,var(--color-brand-purple)_10%,transparent)]",
    iconColorClass: "text-[var(--color-brand-purple)]",
  },
};
