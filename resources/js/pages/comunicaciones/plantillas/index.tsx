import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Plantillas"
            description="Plantillas reutilizables para recordatorios y campañas."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Plantillas', href: '/comunicaciones/plantillas' },
    ],
};