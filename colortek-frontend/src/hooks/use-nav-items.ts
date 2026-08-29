"use client";

import { useAuth } from "@/context/auth-context";
import { usePermissions } from "@/hooks/use-permissions";
import type { NavItemConfig } from "@/components/common/sidebar/data";
import { MAIN_NAV_ITEMS } from "@/components/common/sidebar/data";
import { useMemo } from "react";

export function useFilteredNavItems(items: NavItemConfig[] = MAIN_NAV_ITEMS) {
  const { can } = usePermissions();
  const { user } = useAuth();

  return useMemo(() => {
    const departmentCodes =
      user?.departments?.map((d) => d.code.toLowerCase()) ?? [];

    return items.filter((item) => {
      if (item.permission && !can(item.permission)) {
        return false;
      }

      if (item.departmentCodes?.length) {
        const inDept = item.departmentCodes.some((code) =>
          departmentCodes.includes(code.toLowerCase()),
        );
        if (!inDept && !can("project.view_all")) {
          return false;
        }
      }

      return true;
    });
  }, [can, items, user?.departments]);
}

export function useNavItemVisibility() {
  const { can } = usePermissions();
  const { user } = useAuth();

  return useMemo(() => {
    const departmentCodes =
      user?.departments?.map((d) => d.code.toLowerCase()) ?? [];

    return (item: { permission?: string; departmentCodes?: string[] }) => {
      if (item.permission && !can(item.permission)) {
        return false;
      }

      if (item.departmentCodes?.length) {
        const inDept = item.departmentCodes.some((code) =>
          departmentCodes.includes(code.toLowerCase()),
        );
        if (!inDept && !can("project.view_all")) {
          return false;
        }
      }

      return true;
    };
  }, [can, user?.departments]);
}
