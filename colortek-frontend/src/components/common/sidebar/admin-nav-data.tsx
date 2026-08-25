import { Widget4Icon } from "./icon";

export const ADMIN_NAV = {
  label: "ADMIN",
  items: [
    {
      title: "Administration",
      icon: <Widget4Icon />,
      items: [
        { title: "Calendar", url: "/admin/calendar", permission: "settings.manage|holiday.manage" },
        { title: "Access", url: "/admin/access", permission: "role.manage|user.manage|employee.manage" },
        { title: "Workflows", url: "/admin/workflows", permission: "workflow.view" },
        { title: "Checklist", url: "/admin/checklist", permission: "settings.manage" },
        { title: "Failures", url: "/admin/failures", permission: "settings.manage" },
      ],
    },
  ],
};
