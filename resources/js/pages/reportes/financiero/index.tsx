import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Financiero mensual"
            description="Vista materializada con ventas y CPE por mes."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '#' },
        { title: 'Financiero mensual', href: '/reportes/financiero' },
    ],
};