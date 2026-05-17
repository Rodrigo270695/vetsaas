import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="API logs"
            description="Uso de tokens y endpoints externos."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Auditoría', href: '#' },
        { title: 'API logs', href: '/auditoria/api-logs' },
    ],
};