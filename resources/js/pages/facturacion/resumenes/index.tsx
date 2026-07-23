import { FelAnulacionHistorialPage } from '@/pages/facturacion/components/fel-anulacion-historial-page';

const ROUTE_URL = '/facturacion/resumenes';

export default function Index() {
    return (
        <FelAnulacionHistorialPage
            route_url={ROUTE_URL}
            empty_title="Aún no hay boletas anuladas"
            empty_description="Cuando anules boletas electrónicas desde caja, aparecerán aquí como historial de resúmenes."
            hint="Historial de boletas electrónicas anuladas vía resumen diario Lucode. No genera envíos nuevos a SUNAT."
        />
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Facturación' },
        { title: 'Resúmenes', href: ROUTE_URL },
    ],
};
