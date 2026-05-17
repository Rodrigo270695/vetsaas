import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Logs"
            description="Trazabilidad de cambios sensibles."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Auditoría', href: '#' },
        { title: 'Logs', href: '/auditoria/logs' },
    ],
};