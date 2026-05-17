import PlaceholderPage from '@/components/placeholder-page';

export default function Index() {
    return (
        <PlaceholderPage
            title="Documentos"
            description="Comprobantes electrónicos emitidos a SUNAT."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación', href: '#' },
        { title: 'Documentos', href: '/facturacion/documentos' },
    ],
};