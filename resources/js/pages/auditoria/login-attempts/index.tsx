import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Login attempts"
            description="Intentos de acceso (exitosos y fallidos)."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Auditoría', href: '#' },
        { title: 'Login attempts', href: '/auditoria/login-attempts' },
    ],
};