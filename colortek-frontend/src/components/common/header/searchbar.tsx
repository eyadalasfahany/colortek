'use client';

import { SearchIcon } from '@/components/common/header/icons';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/tailgrids/core/input-group';
import { globalSearch } from '@/services/searchService';
import type { SearchResultItem } from '@/types/notifications';
import { Command } from 'cmdk';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';

const GROUP_LABELS: Record<string, string> = {
  projects: 'Projects',
  tasks: 'Tasks',
  clients: 'Clients',
  samples: 'Samples',
  site_visits: 'Site visits',
  formulas: 'Formulas',
};

function resultHref(item: SearchResultItem): string {
  switch (item.type) {
    case 'project':
      return `/projects/${item.reference ?? item.id}`;
    case 'task':
      return `/tasks/${item.id}`;
    case 'client':
      return `/projects?q=${encodeURIComponent(item.label)}`;
    case 'sample':
      return `/samples/${item.reference ?? item.id}`;
    default:
      return '/projects';
  }
}

export default function SearchBar() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Record<string, SearchResultItem[]>>({});
    const [loading, setLoading] = useState(false);
    const router = useRouter();

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((prev) => !prev);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        if (!query.trim()) {
            setResults({});
            return;
        }

        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const data = await globalSearch(query.trim());
                setResults(data as unknown as Record<string, SearchResultItem[]>);
            } catch {
                setResults({});
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [query]);

    const handleSelect = (href: string) => {
        setOpen(false);
        setQuery('');
        router.push(href);
    };

    const hasResults = Object.values(results).some((items) => items.length > 0);

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className='flex size-10 items-center justify-center rounded-lg border border-card-border bg-card-background text-icon-primary shadow-xs transition-colors outline-none hover:bg-background-gray-primary focus-visible:border-input-primary-focus-border focus-visible:ring-4 focus-visible:ring-input-primary-focus-border/20 xl:hidden'
                aria-label='Open search modal'
            >
                <SearchIcon />
            </button>

            <div className='hidden xl:block'>
                <button
                    onClick={() => setOpen(true)}
                    className='w-full text-left outline-none focus:outline-none'
                    type='button'
                >
                    <InputGroup className='h-10 cursor-pointer'>
                        <InputGroupAddon align='inline-start' className='pr-0 text-icon-tertiary'>
                            <SearchIcon />
                        </InputGroupAddon>
                        <InputGroupInput
                            placeholder='Search projects, tasks, clients…'
                            className='pointer-events-none cursor-pointer pl-2 text-sm select-none'
                            readOnly
                        />
                        <InputGroupAddon align='inline-end'>
                            <div className='rounded-md border border-card-border bg-background-gray-primary/50 px-2 py-0.75 text-xs text-text-tertiary'>
                                <span className='font-medium'>⌘</span> K
                            </div>
                        </InputGroupAddon>
                    </InputGroup>
                </button>
            </div>

            <Command.Dialog
                open={open}
                onOpenChange={setOpen}
                label='Global Search'
                overlayClassName='fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity duration-200'
                contentClassName='fixed top-1/2 left-1/2 z-50 w-full max-w-xl -translate-x-1/2 -translate-y-1/2 rounded-xl border border-card-border bg-card-background text-text-primary shadow-2xl overflow-hidden outline-none max-sm:max-w-[calc(100%-2rem)]'
            >
                <div className='border-b border-card-border p-3.5'>
                    <InputGroup className='h-10'>
                        <InputGroupAddon align='inline-start' className='pr-0 text-icon-tertiary'>
                            <SearchIcon />
                        </InputGroupAddon>
                        <Command.Input
                            value={query}
                            onValueChange={setQuery}
                            placeholder='Search SO9577, colour name, client…'
                            className='w-full min-w-0 flex-1 border-none bg-transparent pl-2 text-sm text-text-primary outline-none placeholder:text-text-tertiary focus:ring-0 focus:outline-none'
                        />
                        <InputGroupAddon align='inline-end'>
                            <div className='rounded-md border border-card-border bg-background-gray-primary/50 px-2 py-0.75 text-xs text-text-tertiary'>
                                ESC
                            </div>
                        </InputGroupAddon>
                    </InputGroup>
                </div>

                <Command.List className='scrollbar-thin max-h-96 overflow-y-auto p-2'>
                    {!query.trim() ? (
                        <div className='py-8 text-center text-sm text-text-tertiary'>
                            Type a project reference, client name, or task code.
                        </div>
                    ) : null}
                    {loading ? (
                        <div className='py-8 text-center text-sm text-text-tertiary'>Searching…</div>
                    ) : null}
                    {!loading && query.trim() && !hasResults ? (
                        <Command.Empty className='py-8 text-center text-sm text-text-tertiary'>
                            No results found.
                        </Command.Empty>
                    ) : null}

                    {Object.entries(results).map(([groupKey, items]) => {
                        if (!items.length) return null;
                        return (
                            <Command.Group
                                key={groupKey}
                                heading={GROUP_LABELS[groupKey] ?? groupKey}
                                className='py-1.5 **:[[cmdk-group-heading]]:px-3 **:[[cmdk-group-heading]]:py-1.5 **:[[cmdk-group-heading]]:text-[11px] **:[[cmdk-group-heading]]:font-semibold **:[[cmdk-group-heading]]:tracking-wider **:[[cmdk-group-heading]]:text-text-tertiary **:[[cmdk-group-heading]]:uppercase'
                            >
                                {items.map((item) => {
                                    const href = resultHref(item);
                                    return (
                                        <Command.Item
                                            key={`${item.type}-${item.id}`}
                                            value={`${item.type} ${item.label}`}
                                            onSelect={() => handleSelect(href)}
                                            className='flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-background-gray-primary data-[selected=true]:bg-background-gray-primary'
                                        >
                                            <span className='truncate text-text-primary'>{item.label}</span>
                                            {item.project_reference ? (
                                                <span className='ml-2 shrink-0 text-xs text-text-tertiary'>
                                                    {item.project_reference}
                                                </span>
                                            ) : null}
                                        </Command.Item>
                                    );
                                })}
                            </Command.Group>
                        );
                    })}
                </Command.List>
            </Command.Dialog>
        </>
    );
}
