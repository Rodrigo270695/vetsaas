import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Cola saliente"
            description="Mensajes pendientes de envío por WhatsApp, email o SMS."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Cola saliente', href: '/comunicaciones/cola' },
    ],
};