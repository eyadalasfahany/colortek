'use client';

import { CollapsibleGroup } from '@/components/tailgrids/core/collapsible';
import { cn } from '@/utils/cn';
import { Logo, LogoWithText, LogoWithTextDark } from '@/utils/icon';
import { useTheme } from 'next-themes';
import { useTranslations } from 'next-intl';
import { useMemo, useState } from 'react';
import type { Key } from 'react-aria-components';
import { useFilteredNavItems } from '@/hooks/use-nav-items';
import { usePermissions } from '@/hooks/use-permissions';
import { Link, usePathname } from '@/i18n/navigation';
import { ADMIN_NAV } from './admin-nav-data';
import { CloseIcon, SidebarExpandedIcon, ThreeDots } from './icon';
import NavItem from './nav-item';
import { findActiveGroupKey } from './utils';

export default function Sidebar({
    isSidebarOpen,
    toggleSidebar,
    isMobileSheet = false,
    onItemClick,
}: {
    isSidebarOpen: boolean;
    toggleSidebar: () => void;
    isMobileSheet?: boolean;
    onItemClick?: () => void;
}) {
    const pathname = usePathname();
    const { theme } = useTheme();
    const { canAny } = usePermissions();
    const mainItems = useFilteredNavItems();
    const t = useTranslations('nav');

    const navSections = useMemo(() => {
        const adminItems = ADMIN_NAV.items
            .map((item) => ({
                titleKey: item.titleKey,
                title: t(item.titleKey),
                icon: item.icon,
                url: undefined as string | undefined,
                items: item.items
                    ?.filter((sub) => {
                        const perms = sub.permission.split('|');
                        return canAny(...perms);
                    })
                    .map((sub) => ({ title: t(sub.titleKey), url: sub.url })),
            }))
            .filter((item) => item.items && item.items.length > 0);

        const sections = [
            {
                label: t('mainMenu'),
                items: mainItems.map((item) => ({
                    titleKey: item.titleKey,
                    title: t(item.titleKey),
                    icon: item.icon,
                    url: item.url,
                    items: (item.items ?? []).map((sub) => ({
                        title: t(sub.titleKey),
                        url: sub.url,
                    })),
                })),
            },
        ];

        if (adminItems.length > 0) {
            sections.push({ label: t(ADMIN_NAV.labelKey), items: adminItems });
        }

        return sections;
    }, [canAny, mainItems, t]);

    const activeGroupKey = useMemo(
        () => findActiveGroupKey(pathname, navSections),
        [pathname, navSections],
    );

    const [expandedKeys, setExpandedKeys] = useState<Set<Key>>(
        () => new Set<Key>(activeGroupKey ? [activeGroupKey] : []),
    );

    return (
        <div className='flex h-full flex-col overflow-hidden'>
            <div
                className={cn(
                    'flex items-center px-4 pt-7 text-text-primary',
                    isSidebarOpen
                        ? 'justify-between'
                        : 'flex-col justify-center gap-4',
                )}
            >
                <Link href='/' onClick={onItemClick}>
                    {isSidebarOpen ? (
                        theme === 'light' ? (
                            <LogoWithText />
                        ) : (
                            <LogoWithTextDark />
                        )
                    ) : (
                        <Logo />
                    )}
                </Link>

                <button
                    onClick={() => toggleSidebar()}
                    className={cn(
                        'p-1.5 transition-colors',
                        isMobileSheet
                            ? 'rounded-lg text-icon-tertiary hover:bg-background-gray-primary hover:text-text-primary'
                            : 'text-icon-tertiary hover:text-text-secondary',
                    )}
                    aria-label={
                        isMobileSheet ? t('sidebar') : isSidebarOpen ? t('collapseSidebar') : t('expandSidebar')
                    }
                >
                    {isMobileSheet ? <CloseIcon /> : <SidebarExpandedIcon />}
                </button>
            </div>

            <nav
                className={cn(
                    'scrollbar-thin flex-1 overflow-y-auto',
                    isSidebarOpen ? 'mt-7 space-y-6 px-4' : 'mt-5 px-2',
                )}
            >
                <CollapsibleGroup
                    expandedKeys={expandedKeys}
                    onExpandedChange={setExpandedKeys}
                >
                    {navSections.map((section) => (
                        <div key={section.label}>
                            {isSidebarOpen ? (
                                <p className='mt-6 mb-4 text-xs text-text-tertiary uppercase'>
                                    {section.label}
                                </p>
                            ) : (
                                section.label && (
                                    <span className='flex items-center justify-center pt-6 pb-4 text-icon-secondary'>
                                        <ThreeDots />
                                    </span>
                                )
                            )}

                            <div
                                className={cn(
                                    'space-y-1',
                                    !isSidebarOpen && 'space-y-1.5',
                                )}
                            >
                                {section.items.map((item) => (
                                    <NavItem
                                        key={item.title}
                                        id={item.title}
                                        icon={item.icon}
                                        label={item.title}
                                        href={item.url}
                                        items={item.items}
                                        collapsed={!isSidebarOpen}
                                        onItemClick={onItemClick}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </CollapsibleGroup>
            </nav>
        </div>
    );
}
