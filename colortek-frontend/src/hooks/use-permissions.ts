"use client";

import { useAuth } from "@/context/auth-context";
import { useCallback, useMemo } from "react";

export function usePermissions() {
  const { user } = useAuth();
  const permissions = user?.permissions ?? [];

  const can = useCallback(
    (permission: string) => permissions.includes(permission),
    [permissions],
  );

  const canAny = useCallback(
    (...names: string[]) => names.some((name) => permissions.includes(name)),
    [permissions],
  );

  const canAccessAdmin = useMemo(
    () =>
      canAny(
        "settings.manage",
        "holiday.manage",
        "role.manage",
        "user.manage",
        "employee.manage",
        "workflow.view",
      ),
    [canAny],
  );

  return { can, canAny, canAccessAdmin, permissions };
}
