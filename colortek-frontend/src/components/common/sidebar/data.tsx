"use client";

import {
  AlphabetIcon,
  HomeIcon,
  PieChartIcon,
  TableIcon,
  UserIcon,
  Widget4Icon,
  WindowIcon,
} from "./icon";

export interface NavItemConfig {
  /** next-intl key under `nav` */
  titleKey: string;
  url?: string;
  icon: React.ReactNode;
  permission?: string;
  departmentCodes?: string[];
  items?: Array<{ titleKey: string; url: string; permission?: string }>;
}

/** Production navigation per specs/09-screens/00-screen-map.md */
export const MAIN_NAV_ITEMS: NavItemConfig[] = [
  {
    titleKey: "controlRoom",
    url: "/",
    icon: <HomeIcon />,
    permission: "project.view_all",
  },
  {
    titleKey: "myTasks",
    url: "/my-tasks",
    icon: <TableIcon />,
  },
  {
    titleKey: "queue",
    url: "/queue",
    icon: <TableIcon />,
    permission: "task.view_own_queue",
  },
  {
    titleKey: "projects",
    url: "/projects",
    icon: <WindowIcon />,
    permission: "project.view",
  },
  {
    titleKey: "samples",
    url: "/samples",
    icon: <TableIcon />,
    permission: "sample.view",
  },
  {
    titleKey: "site",
    url: "/site",
    icon: <WindowIcon />,
    permission: "site.view",
  },
  {
    titleKey: "workshop",
    url: "/workshop",
    icon: <Widget4Icon />,
    departmentCodes: ["workshop", "tinting"],
  },
  {
    titleKey: "journal",
    url: "/journal",
    icon: <AlphabetIcon />,
    permission: "journal.view",
  },
  {
    titleKey: "peopleHours",
    url: "/people-hours",
    icon: <UserIcon />,
    permission: "time.view_all",
  },
  {
    titleKey: "activity",
    url: "/activity",
    icon: <PieChartIcon />,
  },
];

export const MOBILE_PRIMARY_NAV = [
  { titleKey: "myTasks", url: "/my-tasks" },
  { titleKey: "queue", url: "/queue", permission: "task.view_own_queue" },
  { titleKey: "projects", url: "/projects", permission: "project.view" },
] as const;

/** Flat list for legacy search fallback */
export const NAV_DATA = [
  {
    labelKey: "mainMenu",
    items: MAIN_NAV_ITEMS.map((item) => ({
      titleKey: item.titleKey,
      url: item.url,
      icon: item.icon,
      items: item.items ?? [],
    })),
  },
];
