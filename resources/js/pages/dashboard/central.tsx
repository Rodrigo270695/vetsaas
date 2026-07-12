import { Head, Link } from '@inertiajs/react';
import { Activity, Building2, Server } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';

export default function DashboardCentral() {
    const { t } = useTranslation(['dashboard', 'common']);

    return (
        <>
            <Head title={t('central.title')} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('central.title')}
                    description={t('central.description')}
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Server className="size-5 text-primary" aria-hidden />
                            VetSaaS
                        </CardTitle>
                        <CardDescription>{t('central.hint')}</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <Button asChild>
                            <Link href="/plataforma/operaciones">
                                <Activity className="mr-2 size-4" />
                                {t('central.cta_operaciones')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/plataforma/tenants">
                                <Building2 className="mr-2 size-4" />
                                {t('central.cta_tenants')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/plataforma/planes">{t('central.cta_plataforma')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DashboardCentral.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
