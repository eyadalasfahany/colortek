import { Widget4Icon } from "./icon";

export const ADMIN_NAV = {
  labelKey: "admin",
  items: [
    {
      titleKey: "administration",
      icon: <Widget4Icon />,
      items: [
        { titleKey: "calendar", url: "/admin/calendar", permission: "settings.manage|holiday.manage" },
        { titleKey: "settings", url: "/admin/settings", permission: "settings.manage" },
        { titleKey: "access", url: "/admin/access", permission: "role.manage|user.manage|employee.manage" },
        { titleKey: "workflows", url: "/admin/workflows", permission: "workflow.view" },
        { titleKey: "checklist", url: "/admin/checklist", permission: "settings.manage" },
        { titleKey: "failures", url: "/admin/failures", permission: "settings.manage" },
        { titleKey: "audit", url: "/admin/audit", permission: "audit.view" },
      ],
    },
  ],
};
