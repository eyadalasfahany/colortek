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
  title: string;
  url?: string;
  icon: React.ReactNode;
  permission?: string;
  /** Show when user belongs to a department whose code matches one of these */
  departmentCodes?: string[];
  items?: Array<{ title: string; url: string; permission?: string }>;
}

/** Production navigation per specs/09-screens/00-screen-map.md */
export const MAIN_NAV_ITEMS: NavItemConfig[] = [
  {
    title: "Control Room",
    url: "/",
    icon: <HomeIcon />,
    permission: "project.view_all",
  },
  {
    title: "My Tasks",
    url: "/my-tasks",
    icon: <TableIcon />,
  },
  {
    title: "Queue",
    url: "/queue",
    icon: <TableIcon />,
    permission: "task.view_own_queue",
  },
  {
    title: "Projects",
    url: "/projects",
    icon: <WindowIcon />,
    permission: "project.view",
  },
  {
    title: "Samples",
    url: "/samples",
    icon: <TableIcon />,
    permission: "sample.view",
  },
  {
    title: "Site",
    url: "/site",
    icon: <WindowIcon />,
    permission: "site.view",
  },
  {
    title: "Workshop",
    url: "/workshop",
    icon: <Widget4Icon />,
    departmentCodes: ["workshop", "tinting"],
  },
  {
    title: "Journal",
    url: "/journal",
    icon: <AlphabetIcon />,
    permission: "journal.view",
  },
  {
    title: "People & Hours",
    url: "/people-hours",
    icon: <UserIcon />,
    permission: "time.view_all",
  },
  {
    title: "Activity",
    url: "/activity",
    icon: <PieChartIcon />,
  },
];

export const MOBILE_PRIMARY_NAV = [
  { title: "My Tasks", url: "/my-tasks" },
  { title: "Queue", url: "/queue", permission: "task.view_own_queue" },
  { title: "Projects", url: "/projects", permission: "project.view" },
] as const;

/** Demo/template routes — kept in codebase but hidden from production nav */
export const DEMO_NAV_DATA = [
  {
    label: "DEMO (dev only)",
    items: [
      {
        title: "Forms",
        icon: <AlphabetIcon />,
        items: [{ title: "Form Elements", url: "/form-elements" }],
      },
      {
        title: "Tables",
        icon: <TableIcon />,
        items: [{ title: "Basic Tables", url: "/tables/basic-tables" }],
      },
      {
        title: "Charts",
        icon: <PieChartIcon />,
        items: [
          { title: "Line Charts", url: "/charts/line-charts" },
          { title: "Bar Charts", url: "/charts/bar-charts" },
          { title: "Pie Charts", url: "/charts/pie-charts" },
        ],
      },
      {
        title: "UI Elements",
        icon: <Widget4Icon />,
        items: [
          { title: "Accordion", url: "/ui-elements/accordion" },
          { title: "Avatars", url: "/ui-elements/avatars" },
          { title: "Buttons", url: "/ui-elements/buttons" },
        ],
      },
    ],
  },
];

/** Flat list for legacy search fallback */
export const NAV_DATA = [
  {
    label: "MAIN MENU",
    items: MAIN_NAV_ITEMS.map((item) => ({
      title: item.title,
      url: item.url,
      icon: item.icon,
      items: item.items ?? [],
    })),
  },
];
