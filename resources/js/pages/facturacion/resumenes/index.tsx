import {
    FelAnulacionHistorialPage,
    felAnulacionLayout,
    type FelAnulacionHistorialProps,
} from '@/pages/facturacion/components/fel-anulacion-historial-page';

const ROUTE_URL = '/facturacion/resumenes';

type Props = Omit<FelAnulacionHistorialProps, 'route_url' | 'empty_title' | 'empty_description' | 'hint'>;

export default function Index(props: Props) {
    return (
        <FelAnulacionHistorialPage
            {...props}
            route_url={ROUTE_URL}
            empty_title="Aún no hay boletas anuladas"
            empty_description="Cuando anules boletas electrónicas desde caja, aparecerán aquí como historial de resúmenes."
            hint="Historial de boletas electrónicas anuladas vía resumen diario Lucode. No genera envíos nuevos a SUNAT."
        />
    );
}

Index.layout = felAnulacionLayout('Resúmenes', ROUTE_URL);
