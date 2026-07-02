import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, LayoutGrid, Lock, SearchX, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type HttpErrorPageProps = {
    status: 403 | 404 | 500 | 503;
    message?: string | null;
    attempted_path?: string | null;
    is_authenticated?: boolean;
};

export function HttpErrorPage({
    status,
    message,
    attempted_path,
    is_authenticated = false,
}: HttpErrorPageProps) {
    const { t } = useTranslation('common');

    const isForbidden = status === 403;
    const isServerError = status === 500 || status === 503;
    const Icon = isForbidden ? Lock : isServerError ? TriangleAlert : SearchX;
    const title = isForbidden
        ? t('http_errors.forbidden.title')
        : isServerError
          ? t('http_errors.server_error.title')
          : t('http_errors.not_found.title');
    const description = isForbidden
        ? t('http_errors.forbidden.description')
        : isServerError
          ? t('http_errors.server_error.description')
          : t('http_errors.not_found.description');

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex min-h-[60vh] w-full max-w-2xl flex-col items-center justify-center gap-6 px-4 py-12 text-center sm:py-16">
                <div
                    className={
                        isForbidden
                            ? 'flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:ring-amber-900'
                            : isServerError
                              ? 'flex h-20 w-20 items-center justify-center rounded-2xl bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:ring-rose-900'
                              : 'flex h-20 w-20 items-center justify-center rounded-2xl bg-muted text-muted-foreground ring-1 ring-border'
                    }
                >
                    <Icon className="size-10" aria-hidden />
                </div>

                <div className="space-y-2">
                    <p className="text-sm font-medium tracking-wide text-muted-foreground">{status}</p>
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{title}</h1>
                    <p className="mx-auto max-w-lg text-sm text-muted-foreground sm:text-base">{description}</p>
                </div>

                <div className="flex w-full flex-col items-center gap-2 sm:w-auto sm:flex-row sm:gap-3">
                    {is_authenticated ? (
                        <Button asChild size="lg" className="w-full gap-2 sm:w-auto">
                            <Link href={dashboard()} prefetch>
                                <LayoutGrid className="size-4" />
                                {t('http_errors.cta_dashboard')}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild size="lg" className="w-full gap-2 sm:w-auto">
                            <Link href="/login">
                                <Home className="size-4" />
                                {t('http_errors.cta_login')}
                            </Link>
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="lg"
                        variant="outline"
                        className="w-full gap-2 sm:w-auto"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft className="size-4" />
                        {t('http_errors.cta_back')}
                    </Button>
                </div>

                {(message || attempted_path) && (
                    <Card className="w-full text-left">
                        <CardHeader>
                            <CardTitle className="text-base">{t('http_errors.details_title')}</CardTitle>
                            <CardDescription>{t('http_errors.details_hint')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm text-muted-foreground">
                            {attempted_path ? (
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span>{t('http_errors.attempted_path')}:</span>
                                    <code className="rounded bg-muted/60 px-1.5 py-0.5 font-mono text-foreground">
                                        {attempted_path}
                                    </code>
                                </div>
                            ) : null}
                            {message ? (
                                <p className="whitespace-pre-line rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-foreground">
                                    {message}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                )}

                <p className="max-w-xl text-xs leading-relaxed text-muted-foreground sm:text-sm">
                    {isForbidden
                        ? t('http_errors.forbidden.helper')
                        : isServerError
                          ? t('http_errors.server_error.helper')
                          : t('http_errors.not_found.helper')}
                </p>
            </div>
        </>
    );
}
