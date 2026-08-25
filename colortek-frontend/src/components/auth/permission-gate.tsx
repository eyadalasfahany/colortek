"use client";

import { usePermissions } from "@/hooks/use-permissions";
import { Card, CardDescription } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import type { ReactNode } from "react";

interface PermissionGateProps {
  permission?: string;
  permissions?: string[];
  fallback?: ReactNode;
  loadingFallback?: ReactNode;
  children: ReactNode;
}

export default function PermissionGate({
  permission,
  permissions,
  fallback,
  loadingFallback,
  children,
}: PermissionGateProps) {
  const { can, canAny } = usePermissions();

  if (permission && !can(permission)) {
    return (
      fallback ?? (
        <Card className="mx-4 mt-6">
          <CardDescription className="text-center text-text-secondary">
            You do not have permission to view this screen.
          </CardDescription>
        </Card>
      )
    );
  }

  if (permissions?.length && !canAny(...permissions)) {
    return (
      fallback ?? (
        <Card className="mx-4 mt-6">
          <CardDescription className="text-center text-text-secondary">
            You do not have permission to view this screen.
          </CardDescription>
        </Card>
      )
    );
  }

  if (loadingFallback) {
    return loadingFallback;
  }

  return children;
}

export function PermissionGateSkeleton() {
  return (
    <div className="space-y-4 px-4 pt-6 lg:px-6">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-32 w-full" />
    </div>
  );
}
