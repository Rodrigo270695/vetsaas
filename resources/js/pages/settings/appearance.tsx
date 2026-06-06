import { Head, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation('settings');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: t('appearance.title'),
                    href: editAppearance(),
                },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    return (
        <>
            <Head title={t('appearance.title')} />

            <h1 className="sr-only">{t('appearance.title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('appearance.section_title')}
                    description={t('appearance.section_description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
