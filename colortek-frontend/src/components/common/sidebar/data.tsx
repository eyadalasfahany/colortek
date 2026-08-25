import { HomeIcon, TableIcon, UserIcon } from "./icon";

export interface NavItem {
  title: string;
  url?: string;
  icon?: React.ReactNode;
  permission?: string;
  items?: NavItem[];
}

export const NAV_DATA: Array<{ label: string; items: NavItem[] }> = [
  {
    label: "MAIN MENU",
    items: [
      { title: "Control Room", url: "/", icon: <HomeIcon />, permission: "project.view_all" },
      { title: "Projects", url: "/projects", icon: <TableIcon />, permission: "project.view" },
      {
        title: "Tasks",
        icon: <TableIcon />,
        items: [
          { title: "Queue", url: "/queue" },
          { title: "My Tasks", url: "/my-tasks" },
        ],
      },
      { title: "Workshop", url: "/workshop", icon: <TableIcon />, permission: "workshop.view" },
      { title: "Site", url: "/site", icon: <TableIcon />, permission: "site.view" },
      { title: "Samples board", url: "/samples-dashboard", icon: <TableIcon />, permission: "sample.view" },
      { title: "Profile", url: "/profile", icon: <UserIcon /> },
    ],
  },
];

export function filterNavByPermissions(
  data: typeof NAV_DATA,
  permissions: string[],
): typeof NAV_DATA {
  const has = (p?: string) => !p || permissions.includes(p) || permissions.includes("project.view_all");

  return data
    .map((section) => ({
      ...section,
      items: section.items
        .map((item) => ({
          ...item,
          items: item.items?.filter((sub) => has(sub.permission)),
        }))
        .filter((item) => has(item.permission) || (item.items && item.items.length > 0)),
    }))
    .filter((section) => section.items.length > 0);
}
