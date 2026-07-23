import {
    FelAnulacionHistorialPage,
    felAnulacionLayout,
    type FelAnulacionHistorialProps,
} from '@/pages/facturacion/components/fel-anulacion-historial-page';

const ROUTE_URL = '/facturacion/notas-baja';

type Props = Omit<FelAnulacionHistorialProps, 'route_url' | 'empty_title' | 'empty_description' | 'hint'>;

export default function Index(props: Props) {
    return (
        <FelAnulacionHistorialPage
            {...props}
            route_url={ROUTE_URL}
            empty_title="Aún no hay facturas anuladas"
            empty_description="Cuando anules facturas electrónicas desde caja, aparecerán aquí como historial de notas de baja."
            hint="Historial de facturas electrónicas anuladas vía comunicación de baja Lucode. No genera envíos nuevos a SUNAT."
        />
    );
}

Index.layout = felAnulacionLayout('Notas de baja', ROUTE_URL);
