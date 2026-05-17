import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Histórico"
            description="Registro inmutable de mensajes enviados."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Histórico', href: '/comunicaciones/historico' },
    ],
};