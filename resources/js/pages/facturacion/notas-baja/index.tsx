import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Notas de baja"
            description="Comunicaciones de baja a SUNAT."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación', href: '#' },
        { title: 'Notas de baja', href: '/facturacion/notas-baja' },
    ],
};