import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    ChevronDown,
    ChevronRight,
    CircleHelp,
    Package,
    Receipt,
    Search,
    Stethoscope,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Input } from '@/components/ui/input';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { HELP_CATEGORIES } from './help-articles';

const CATEGORY_ICONS = {
    setup: Building2,
    clinic: Stethoscope,
    caja: Wallet,
    inventario: Package,
    facturacion: Receipt,
} as const;

function normalizeSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .trim();
}

export default function ConfiguracionAyudaIndex() {
    const { t } = useTranslation('ayuda');
    const { can } = usePermission();
    const [query, setQuery] = useState('');

    const normalizedQuery = normalizeSearch(query);

    const visibleCategories = useMemo(() => {
        return HELP_CATEGORIES.map((category) => {
            const articles = category.articles
                .filter((article) => !article.permission || can(article.permission))
                .map((article) => {
                    const title = t(`articles.${article.id}.title`);
                    const summary = t(`articles.${article.id}.summary`);
                    const steps = t(`articles.${article.id}.steps`, {
                        returnObjects: true,
                    }) as string[];

                    const haystack = normalizeSearch(
                        [title, summary, ...(Array.isArray(steps) ? steps : [])].join(' '),
                    );

                    const matches =
                        normalizedQuery === '' || haystack.includes(normalizedQuery);

                    return { ...article, title, summary, steps, matches };
                })
                .filter((article) => article.matches);

            return {
                ...category,
                title: t(`categories.${category.id}.title`),
                articles,
            };
        }).filter((category) => category.articles.length > 0);
    }, [can, normalizedQuery, t]);

    return (
        <>
            <Head title={t('page.title')} />

            <div className="flex min-w-0 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={t('page.title')} description={t('page.description')} />

                <div className="relative max-w-md">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('page.search')}
                        className="pl-9"
                        aria-label={t('page.search')}
                    />
                </div>

                {visibleCategories.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-14 text-center">
                        <CircleHelp className="size-10 text-muted-foreground/60" aria-hidden />
                        <p className="max-w-sm text-sm text-muted-foreground">{t('page.empty')}</p>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {visibleCategories.map((category) => {
                            const Icon = CATEGORY_ICONS[category.id as keyof typeof CATEGORY_ICONS] ?? CircleHelp;

                            return (
                                <section
                                    key={category.id}
                                    className="rounded-xl border border-border/70 bg-card shadow-sm"
                                >
                                    <div className="flex items-center gap-2.5 border-b border-border/60 px-4 py-3">
                                        <div className="flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">
                                            <Icon className="size-4" aria-hidden />
                                        </div>
                                        <h2 className="text-sm font-semibold text-foreground">
                                            {category.title}
                                        </h2>
                                    </div>

                                    <div className="divide-y divide-border/50 px-2 py-1">
                                        {category.articles.map((article) => (
                                            <details
                                                key={article.id}
                                                className="group px-2 py-1"
                                                open={category.articles[0]?.id === article.id}
                                            >
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md py-2.5 text-sm font-medium text-foreground marker:content-none hover:bg-muted/40 [&::-webkit-details-marker]:hidden">
                                                    <span>{article.title}</span>
                                                    <ChevronDown
                                                        className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                                                        aria-hidden
                                                    />
                                                </summary>
                                                <div className="space-y-3 pb-3 pl-1 text-sm">
                                                    <p className="text-muted-foreground">{article.summary}</p>
                                                    {Array.isArray(article.steps) && article.steps.length > 0 && (
                                                        <ol className="list-decimal space-y-1.5 pl-4 text-muted-foreground">
                                                            {article.steps.map((step, idx) => (
                                                                <li key={idx} className="leading-relaxed">
                                                                    {step}
                                                                </li>
                                                            ))}
                                                        </ol>
                                                    )}
                                                    {article.href && (
                                                        <Link
                                                            href={article.href}
                                                            className={cn(
                                                                'inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300',
                                                            )}
                                                        >
                                                            {t(`articles.${article.id}.cta`)}
                                                            <ChevronRight className="size-3.5" aria-hidden />
                                                        </Link>
                                                    )}
                                                </div>
                                            </details>
                                        ))}
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                )}

                <p className="text-xs text-muted-foreground">{t('page.footer')}</p>
            </div>
        </>
    );
}

ConfiguracionAyudaIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
            { title: 'Centro de ayuda', href: '/configuracion/ayuda' },
        ]}
    >
        {page}
    </AppLayout>
);
