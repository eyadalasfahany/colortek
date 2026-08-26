type NavChild = { title?: string; url?: string };
type NavItem = {
  title?: string;
  titleKey?: string;
  url?: string;
  items?: NavChild[];
};
type NavSection = Array<{ label?: string; labelKey?: string; items: NavItem[] }>;

/**
 * Checks if the current pathname matches the target href, or if the pathname is a subpath of the target href.
 */
export function isPathActive(href: string, pathname: string): boolean {
  if (!href) return false;

  if (href === "/") {
    return pathname === "/";
  }

  return pathname === href || pathname.startsWith(href + "/");
}

/**
 * Find the nav group whose child URLs include the current pathname.
 * Returns the group's `title` (used as the Disclosure `id`) or null.
 */
export function findActiveGroupKey(pathname: string, sections: NavSection): string | null {
  for (const section of sections) {
    for (const item of section.items) {
      if (item.items && item.items.length > 0) {
        const hasMatch = item.items.some((child) => child.url && isPathActive(child.url, pathname));
        if (hasMatch) return item.title ?? item.titleKey ?? null;
      }
    }
  }
  return null;
}
