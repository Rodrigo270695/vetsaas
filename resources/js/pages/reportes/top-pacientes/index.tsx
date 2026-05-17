import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Top pacientes"
            description="Ranking de pacientes por número de consultas."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '#' },
        { title: 'Top pacientes', href: '/reportes/top-pacientes' },
    ],
};