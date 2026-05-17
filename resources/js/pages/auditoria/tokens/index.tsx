import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Tokens"
            description="Tokens de acceso API personal."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Auditoría', href: '#' },
        { title: 'Tokens', href: '/auditoria/tokens' },
    ],
};