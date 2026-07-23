import { FelAnulacionHistorialPage } from '@/pages/facturacion/components/fel-anulacion-historial-page';

const ROUTE_URL = '/facturacion/notas-baja';

export default function Index() {
    return (
        <FelAnulacionHistorialPage
            route_url={ROUTE_URL}
            empty_title="Aún no hay facturas anuladas"
            empty_description="Cuando anules facturas electrónicas desde caja, aparecerán aquí como historial de notas de baja."
            hint="Historial de facturas electrónicas anuladas vía comunicación de baja Lucode. No genera envíos nuevos a SUNAT."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación' },
        { title: 'Notas de baja', href: ROUTE_URL },
    ],
};
