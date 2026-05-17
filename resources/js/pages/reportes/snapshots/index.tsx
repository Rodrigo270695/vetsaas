import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Snapshots"
            description="Métricas diarias agregadas listas para dashboards."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '#' },
        { title: 'Snapshots', href: '/reportes/snapshots' },
    ],
};