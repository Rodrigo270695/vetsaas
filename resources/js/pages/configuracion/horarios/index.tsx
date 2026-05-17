import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Horarios"
            description="Disponibilidad de cada veterinario por día."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Configuración', href: '#' },
        { title: 'Horarios', href: '/configuracion/horarios' },
    ],
};