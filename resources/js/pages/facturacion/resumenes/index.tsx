import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Resúmenes"
            description="Resúmenes diarios de boletas electrónicas."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación', href: '#' },
        { title: 'Resúmenes', href: '/facturacion/resúmenes' },
    ],
};