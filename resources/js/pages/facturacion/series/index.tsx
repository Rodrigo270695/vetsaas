import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Series"
            description="Series autorizadas por tipo de comprobante y sede."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación', href: '#' },
        { title: 'Series', href: '/facturacion/series' },
    ],
};