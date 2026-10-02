import { router, usePage } from '@inertiajs/react';
import { Loader2, Search, UserPlus, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { PropietarioFormModal } from '@/pages/clinica/propietarios/components/propietario-form-modal';
import type { GeoOption } from '@/pages/clinica/propietarios/types';

type SearchHit = {
    id: string;
    label: string;
    keywords: string;
};

const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

export function HeaderClinicaAcciones() {
    const { tenant } = usePage().props;
    const { can } = usePermission();
    const { t } = useTranslation('common');
    const listId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<SearchHit[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const [ownerOpen, setOwnerOpen] = useState(false);
    const [departamentos, setDepartamentos] = useState<GeoOption[]>([]);

    const canSearch = can('pacientes.view');
    const canCreateOwner = can('propietarios.create');

    useEffect(() => {
        if (!searchOpen) {
            return;
        }

        const frame = window.requestAnimationFrame(() => inputRef.current?.focus());

        return () => window.cancelAnimationFrame(frame);
    }, [searchOpen]);

    useEffect(() => {
        if (!searchOpen) {
            return;
        }

        const onPointer = (event: MouseEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setSearchOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointer);

        return () => document.removeEventListener('mousedown', onPointer);
    }, [searchOpen]);

    useEffect(() => {
        if (!searchOpen) {
            return;
        }

        const q = query.trim();

        if (q.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            void fetch(`/clinica/pacientes/opciones?q=${encodeURIComponent(q)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(async (res) => {
                    if (!res.ok) {
                        setHits([]);

                        return;
                    }

                    const json = (await res.json()) as { data?: SearchHit[] };
                    setHits(Array.isArray(json.data) ? json.data : []);
                    setActive(0);
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setHits([]);
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                });
        }, 220);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query, searchOpen]);

    if (tenant == null || (!canSearch && !canCreateOwner)) {
        return null;
    }

    const showResults = searchOpen && query.trim().length >= 2;
    const closeSearch = () => {
        setSearchOpen(false);
        setQuery('');
        setHits([]);
    };

    const openHit = (hit: SearchHit) => {
        closeSearch();
        router.visit(`/clinica/pacientes/${hit.id}`);
    };

    const openOwner = () => {
        setOwnerOpen(true);

        if (departamentos.length > 0) {
            return;
        }

        void fetch('/geo/departamentos', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    return;
                }

                const json = (await res.json()) as GeoOption[];

                if (Array.isArray(json)) {
                    setDepartamentos(json);
                }
            })
            .catch(() => {
                // El formulario sigue usable; el ubigeo se puede completar después.
            });
    };

    return (
        <>
            <div ref={rootRef} className="relative flex items-center">
                {canSearch ? (
                    <div
                        className={cn(
                            'absolute top-1/2 right-full z-30 mr-1 flex h-9 -translate-y-1/2 items-center overflow-hidden rounded-full border bg-white shadow-sm dark:bg-background',
                            'transition-[width,opacity,border-color,box-shadow] duration-300',
                            searchOpen
                                ? 'w-[min(18rem,calc(100vw-7.5rem))] border-border/80 opacity-100 shadow-md max-sm:fixed max-sm:top-3 max-sm:right-2 max-sm:left-12 max-sm:z-40 max-sm:w-auto max-sm:translate-y-0'
                                : 'pointer-events-none w-0 border-transparent opacity-0 shadow-none',
                        )}
                        style={{ transitionTimingFunction: EASE }}
                    >
                        <input
                            ref={inputRef}
                            value={query}
                            onChange={(event) => {
                                const next = event.target.value;
                                setQuery(next);

                                if (next.trim().length < 2) {
                                    setHits([]);
                                    setLoading(false);
                                }
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    event.preventDefault();
                                    closeSearch();

                                    return;
                                }

                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setActive((index) => Math.min(index + 1, Math.max(hits.length - 1, 0)));

                                    return;
                                }

                                if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setActive((index) => Math.max(index - 1, 0));

                                    return;
                                }

                                if (event.key === 'Enter' && hits[active]) {
                                    event.preventDefault();
                                    openHit(hits[active]);
                                }
                            }}
                            placeholder={t('header_clinica.buscar_placeholder')}
                            aria-label={t('header_clinica.buscar')}
                            aria-controls={listId}
                            aria-expanded={showResults}
                            className="h-full min-w-0 flex-1 bg-transparent px-3.5 text-sm outline-none placeholder:text-muted-foreground"
                        />
                        {query !== '' ? (
                            <button
                                type="button"
                                className="mr-1.5 flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                onClick={() => {
                                    setQuery('');
                                    inputRef.current?.focus();
                                }}
                                aria-label={t('header_clinica.buscar_limpiar')}
                            >
                                <X className="size-3.5" strokeWidth={2.25} />
                            </button>
                        ) : null}
                    </div>
                ) : null}

                {canSearch && showResults ? (
                    <div
                        id={listId}
                        role="listbox"
                        className="absolute top-[calc(100%+0.4rem)] right-full z-40 mr-1 w-[min(18rem,calc(100vw-7.5rem))] origin-top-right overflow-hidden rounded-xl border border-border/70 bg-popover shadow-lg motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-top-1 motion-safe:duration-200 max-sm:fixed max-sm:top-14 max-sm:right-2 max-sm:left-12 max-sm:w-auto"
                    >
                        {loading && hits.length === 0 ? (
                            <p className="flex items-center gap-2 px-3 py-2.5 text-sm text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" />
                                {t('header_clinica.buscar_cargando')}
                            </p>
                        ) : hits.length === 0 ? (
                            <p className="px-3 py-2.5 text-sm text-muted-foreground">
                                {t('header_clinica.buscar_vacio')}
                            </p>
                        ) : (
                            <ul className="max-h-72 overflow-y-auto py-1">
                                {hits.map((hit, index) => (
                                    <li key={hit.id}>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected={index === active}
                                            className={cn(
                                                'flex w-full cursor-pointer flex-col items-start gap-0.5 px-3 py-2 text-left transition-colors',
                                                index === active
                                                    ? 'bg-primary/8 text-foreground'
                                                    : 'hover:bg-muted/70',
                                            )}
                                            onMouseEnter={() => setActive(index)}
                                            onClick={() => openHit(hit)}
                                        >
                                            <span className="truncate text-sm font-medium">{hit.label}</span>
                                            {hit.keywords ? (
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {hit.keywords}
                                                </span>
                                            ) : null}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ) : null}

                {canSearch ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className={cn(
                                    'size-8 shrink-0 cursor-pointer text-sky-700 hover:bg-sky-50 hover:text-sky-800 sm:size-9 dark:text-sky-300 dark:hover:bg-sky-950/40',
                                    searchOpen && 'bg-sky-50 text-sky-800 dark:bg-sky-950/40',
                                )}
                                aria-label={t('header_clinica.buscar')}
                                aria-expanded={searchOpen}
                                onClick={() => {
                                    if (searchOpen) {
                                        closeSearch();

                                        return;
                                    }

                                    setSearchOpen(true);
                                }}
                            >
                                <Search className="size-4" strokeWidth={2.25} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">{t('header_clinica.buscar')}</TooltipContent>
                    </Tooltip>
                ) : null}

                {canCreateOwner ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 cursor-pointer text-indigo-700 hover:bg-indigo-50 hover:text-indigo-800 sm:size-9 dark:text-indigo-300 dark:hover:bg-indigo-950/40"
                                aria-label={t('header_clinica.nuevo_propietario')}
                                onClick={openOwner}
                            >
                                <UserPlus className="size-4" strokeWidth={2.25} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">{t('header_clinica.nuevo_propietario')}</TooltipContent>
                    </Tooltip>
                ) : null}
            </div>

            {canCreateOwner ? (
                <PropietarioFormModal
                    open={ownerOpen}
                    onOpenChange={setOwnerOpen}
                    propietario={null}
                    departamentos={departamentos}
                />
            ) : null}
        </>
    );
}
