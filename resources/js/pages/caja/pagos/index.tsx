import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Pagos"
            description="Pagos parciales y cobranzas asociadas a ventas."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Caja', href: '#' },
        { title: 'Pagos', href: '/caja/pagos' },
    ],
};