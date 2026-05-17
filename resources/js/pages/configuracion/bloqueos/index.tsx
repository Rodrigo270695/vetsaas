import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Bloqueos"
            description="Bloqueos de agenda por vacaciones o feriados."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Configuración', href: '#' },
        { title: 'Bloqueos', href: '/configuracion/bloqueos' },
    ],
};