"use client";

import { usePermissions } from "@/hooks/use-permissions";
import { Spinner } from "@/components/tailgrids/core/spinner";
import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

export default function AdminGuard({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { canAccessAdmin } = usePermissions();

  useEffect(() => {
    if (!canAccessAdmin) {
      router.replace("/error-page");
    }
  }, [canAccessAdmin, router]);

  if (!canAccessAdmin) {
    return (
      <div className="flex min-h-80 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return children;
}
