"use client";

import { MAIN_NAV_ITEMS, MOBILE_PRIMARY_NAV } from "@/components/common/sidebar/data";
import { useCanSeeNavItem, useFilteredNavItems } from "@/hooks/use-nav-items";
import { cn } from "@/utils/cn";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useMemo, useState } from "react";
import { SheetContent, SheetOverlay, SheetTitle } from "@/components/tailgrids/core/sheet";

export default function MobileBottomNav() {
  const pathname = usePathname();
  const [moreOpen, setMoreOpen] = useState(false);
  const allItems = useFilteredNavItems();
  const canSee = useCanSeeNavItem;

  const primaryItems = useMemo(
    () =>
      MOBILE_PRIMARY_NAV.filter((item) =>
        "permission" in item && item.permission
          ? canSee({ permission: item.permission })
          : true,
      ),
    [canSee],
  );

  const moreItems = useMemo(
    () =>
      allItems.filter(
        (item) =>
          item.url &&
          !primaryItems.some((p) => p.url === item.url) &&
          item.url !== "/my-tasks",
      ),
    [allItems, primaryItems],
  );

  const isMoreActive = moreItems.some((item) => item.url === pathname);

  return (
    <>
      <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-card-border bg-card-surface-area pb-[env(safe-area-inset-bottom)] xl:hidden">
        <div className="grid grid-cols-4">
          {primaryItems.map((item) => {
            const active = pathname === item.url || pathname.startsWith(`${item.url}/`);

            return (
              <Link
                key={item.url}
                href={item.url}
                className={cn(
                  "flex flex-col items-center gap-1 px-2 py-3 text-xs",
                  active ? "text-brand-500" : "text-text-secondary",
                )}
              >
                <span className="font-medium">{item.title}</span>
              </Link>
            );
          })}
          <button
            type="button"
            onClick={() => setMoreOpen(true)}
            className={cn(
              "flex flex-col items-center gap-1 px-2 py-3 text-xs",
              isMoreActive ? "text-brand-500" : "text-text-secondary",
            )}
          >
            <span className="font-medium">More</span>
          </button>
        </div>
      </nav>

      <SheetOverlay isOpen={moreOpen} onOpenChange={setMoreOpen}>
        <SheetContent side="bottom" className="rounded-t-2xl border-card-border p-0">
          <SheetTitle className="border-b border-card-border px-4 py-3 text-base font-semibold">
            More
          </SheetTitle>
          <ul className="max-h-80 overflow-y-auto py-2">
            {moreItems.map((item) => (
              <li key={item.url}>
                <Link
                  href={item.url!}
                  onClick={() => setMoreOpen(false)}
                  className="block px-4 py-3 text-sm text-text-primary hover:bg-background-gray-primary"
                >
                  {item.title}
                </Link>
              </li>
            ))}
            {MAIN_NAV_ITEMS.filter((i) => i.url === "/activity").map((item) => (
              <li key={item.url}>
                <Link
                  href={item.url!}
                  onClick={() => setMoreOpen(false)}
                  className="block px-4 py-3 text-sm text-text-primary hover:bg-background-gray-primary"
                >
                  {item.title}
                </Link>
              </li>
            ))}
          </ul>
        </SheetContent>
      </SheetOverlay>
    </>
  );
}
