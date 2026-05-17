import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Descuentos"
            description="Promociones, cupones y descuentos aplicables."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Caja', href: '#' },
        { title: 'Descuentos', href: '/caja/descuentos' },
    ],
};