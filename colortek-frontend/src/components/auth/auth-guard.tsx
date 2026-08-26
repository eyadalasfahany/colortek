"use client";

import { useAuth } from "@/context/auth-context";
import { hasToken } from "@/lib/auth-token";
import { Spinner } from "@/components/tailgrids/core/spinner";
import { useEffect, type ReactNode } from "react";
import { usePathname, useRouter } from "@/i18n/navigation";

export default function AuthGuard({ children }: { children: ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { isAuthenticated, isLoading } = useAuth();
  const tokenPresent = hasToken();

  useEffect(() => {
    if (!tokenPresent) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
      return;
    }

    if (!isLoading && !isAuthenticated) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [isAuthenticated, isLoading, pathname, router, tokenPresent]);

  if (!tokenPresent || isLoading || !isAuthenticated) {
    return (
      <div className="flex min-h-80 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return children;
}
