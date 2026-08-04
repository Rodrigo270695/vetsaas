import { Head, resetLayoutProps, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { TicketPrintDialog } from '@/components/tickets/ticket-print-dialog';
import { usePermission } from '@/hooks/use-permission';
import { normalizeTicketAncho } from '@/lib/ticket-ancho';
import { dashboard } from '@/routes';
import { ConsultaCargosMain } from '../../clinica/historias-clinicas/components/consulta-cargos-main';
import { formatAtendidoInAppTimezone } from '../../clinica/historias-clinicas/format-atendido';

type LineaApi = {
    id: string;
    tipo_linea: string;
    producto_id: string | null;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
    descuento_importe: string;
    orden: number;
    producto?: { id: string; nombre: string; sku: string | null; unidad: string | null } | null;
};

type CargoApi = {
    id: string;
    estado: string;
    moneda: string;
    notas: string | null;
    subtotal_sin_igv: string;
    igv_importe: string;
    total: string;
    lineas: LineaApi[];
};

type EstanciaApi = {
    id: string;
    ingreso_at: string;
    tipo_estancia: string;
    paciente: {
        id: string;
        nombre: string;
        propietario?: {
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        };
    };
    responsable: { id: string; name: string } | null;
};

type LineaForm = {
    tipo_linea: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
    descuento_importe: string;
    producto_id: string | null;
    producto_label: string | null;
};

type FormState = {
    notas: string;
    lineas: LineaForm[];
};

const LIST_URL = '/servicios/hotel';

/** Cantidad / importes en UI y envío al servidor: máximo 2 decimales. */
function formatDecimal2(raw: string | number | null | undefined): string {
    if (raw === null || raw === undefined) {
        return '0.00';
    }
    const normalized =
        typeof raw === 'string' ? raw.replace(',', '.').trim() : String(raw);
    if (normalized === '') {
        return '0.00';
    }
    const n = Number(normalized);
    if (!Number.isFinite(n)) {
        return '0.00';
    }

    return n.toFixed(2);
}

function formatCantidadOnBlur(raw: string): string {
    const n = Number(String(raw).replace(',', '.').trim());
    if (!Number.isFinite(n) || n < 0.01) {
        return '1.00';
    }

    return n.toFixed(2);
}

function formatImporteOnBlur(raw: string): string {
    const n = Number(String(raw).replace(',', '.').trim());
    if (!Number.isFinite(n) || n < 0) {
        return '0.00';
    }

    return n.toFixed(2);
}

function formatMonto(amount: string | null, moneda: string, locale: string): string {
    if (amount === null || amount === '') {
        return '—';
    }

    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

function lineaImporteMostrar(ln: {
    cantidad: string;
    precio_unitario: string;
    descuento_importe: string;
}): number {
    const c = Number(ln.cantidad) || 0;
    const p = Number(ln.precio_unitario) || 0;
    const d = Number(ln.descuento_importe) || 0;

    return Math.max(0, Math.round((c * p - d) * 100) / 100);
}

function mapLineasToForm(lineas: LineaApi[]): LineaForm[] {
    return lineas.map((l) => ({
        tipo_linea: l.tipo_linea,
        concepto: l.concepto,
        cantidad: formatDecimal2(l.cantidad),
        precio_unitario: formatDecimal2(l.precio_unitario),
        descuento_importe: formatDecimal2(l.descuento_importe ?? '0'),
        producto_id: l.producto_id,
        producto_label: l.producto?.nombre ?? null,
    }));
}

function emptyLine(): LineaForm {
    return {
        tipo_linea: 'servicio',
        concepto: '',
        cantidad: '1.00',
        precio_unitario: '0.00',
        descuento_importe: '0.00',
        producto_id: null,
        producto_label: null,
    };
}

type CobroApi = {
    venta_id: string | null;
    venta_numero: string | null;
    puede_cobrar: boolean;
    requiere_sesion_caja: boolean;
    url_cobrar: string;
    url_sesiones_caja: string;
    url_cargos_consulta?: string | null;
};

type Props = {
    estancia: EstanciaApi;
    cargo: CargoApi;
    cobro: CobroApi;
    noches_sugeridas?: number;
    clinic_billing: {
        moneda: string;
        igv_porcentaje: number;
        precio_incluye_igv: boolean;
        ticket_ancho_mm: '56' | '58' | '80';
    };
};

export default function HotelCargos({
    estancia,
    cargo,
    cobro,
    noches_sugeridas,
    clinic_billing,
}: Props) {
    const { t, i18n } = useTranslation(['consulta-cargos', 'hotel', 'nav', 'caja']);
    const [ticketModalOpen, setTicketModalOpen] = useState(false);
    const [editandoConfirmada, setEditandoConfirmada] = useState(false);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const puedeEditarCargos = can('consulta-cargos.manage') || can('hotel.update');
    const productosBuscarUrl = `${LIST_URL}/${estancia.id}/cargos/productos-buscar`;
    const serviciosBuscarUrl = `${LIST_URL}/${estancia.id}/cargos/servicios-buscar`;
    const showUrl = LIST_URL;
    const cargosBaseUrl = `${LIST_URL}/${estancia.id}/cargos`;
    const esBorrador = cargo.estado === 'borrador';
    const yaCobrada = cobro.venta_id !== null;
    const puedeEditar =
        puedeEditarCargos && !yaCobrada && (esBorrador || editandoConfirmada);
    const puedeSolicitarEditar =
        puedeEditarCargos && !esBorrador && !yaCobrada && !editandoConfirmada;

    const initial = useMemo<FormState>(
        () => ({
            notas: cargo.notas ?? '',
            lineas:
                cargo.lineas.length > 0 ? mapLineasToForm(cargo.lineas) : [emptyLine()],
        }),
        [cargo],
    );

    const { data, setData, post, processing, errors, clearErrors } = useForm<FormState>(initial);

    const entrarEnEdicion = useCallback(() => {
        setData({
            notas: cargo.notas ?? '',
            lineas:
                cargo.lineas.length > 0 ? mapLineasToForm(cargo.lineas) : [emptyLine()],
        });
        setEditandoConfirmada(true);
    }, [cargo, setData]);

    const title = useMemo(
        () => `${t('hotel:actions.cargos')} · ${estancia.paciente.nombre}`,
        [estancia.paciente.nombre, t],
    );

    const historiasUrl = showUrl;

    const ticketBaseUrl = `${cargosBaseUrl}/ticket`;
    const configTicketAncho = normalizeTicketAncho(clinic_billing.ticket_ancho_mm);

    const abrirTicketEnModal = () => {
        setTicketModalOpen(true);
    };

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('nav:groups.servicios'), href: dashboard().url },
                { title: t('hotel:title'), href: LIST_URL },
                { title: estancia.paciente.nombre, href: showUrl },
                { title: t('breadcrumb_cargos'), href: '#' },
            ],
        });
        return () => {
            resetLayoutProps();
        };
    }, [estancia.paciente.nombre, historiasUrl, showUrl, t]);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!puedeEditar) {
            return;
        }
        post(`${cargosBaseUrl}/confirmar`, {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
                setEditandoConfirmada(false);
            },
        });
    };

    const addLinea = () => {
        setData('lineas', [...data.lineas, emptyLine()]);
    };

    const removeLinea = (idx: number) => {
        setData(
            'lineas',
            data.lineas.filter((_, i) => i !== idx),
        );
    };

    const updateLinea = (idx: number, patch: Partial<LineaForm>) => {
        setData(
            'lineas',
            data.lineas.map((row, i) => (i === idx ? { ...row, ...patch } : row)),
        );
    };

    const hayLineasGuardadas = cargo.lineas.length > 0;

    const sugerirNochesEstadia = () => {
        if (!noches_sugeridas || noches_sugeridas < 1) {
            return;
        }
        const nueva: LineaForm = {
            tipo_linea: 'servicio',
            concepto: estancia.tipo_estancia,
            cantidad: String(noches_sugeridas),
            precio_unitario: '0.00',
            descuento_importe: '0.00',
            producto_id: null,
            producto_label: null,
        };
        setData('lineas', [...data.lineas, nueva]);
    };

    const propietarioLabel = useMemo(() => {
        const pr = estancia.paciente.propietario;
        if (!pr) {
            return '—';
        }

        return (
            pr.razon_social?.trim() ||
            [pr.nombres, pr.apellidos].filter(Boolean).join(' ').trim() ||
            '—'
        );
    }, [estancia.paciente.propietario]);

    const atendidoLabel = formatAtendidoInAppTimezone(estancia.ingreso_at, appLocale, appTz);

    const headerStats = useMemo(
        () => [
            {
                label: t('stat_paciente'),
                value: estancia.paciente.nombre,
                variant: 'default' as const,
            },
            {
                label: t('stat_propietario'),
                value: propietarioLabel,
                variant: 'muted' as const,
            },
            {
                label: t('stat_estado'),
                value: esBorrador ? t('estado.borrador') : t('estado.confirmado'),
                variant: (esBorrador ? 'muted' : 'primary') as 'muted' | 'primary',
            },
        ],
        [estancia.paciente.nombre, esBorrador, propietarioLabel, t],
    );

    const consultaStub = useMemo(
        () => ({
            id: estancia.id,
            veterinario: estancia.responsable
                ? { id: estancia.responsable.id, name: estancia.responsable.name }
                : null,
        }),
        [estancia.id, estancia.responsable],
    );

    const lineasSoloLectura = !puedeEditar && cargo.lineas.length > 0;

    const formatMontoLocal = useCallback(
        (amount: string | null, moneda: string) => formatMonto(amount, moneda, i18n.language),
        [i18n.language],
    );

    return (
        <>
            <Head title={title} />
            <TicketPrintDialog
                open={ticketModalOpen}
                onOpenChange={setTicketModalOpen}
                ticketBaseUrl={ticketBaseUrl}
                configAncho={configTicketAncho}
                title={t('ticket_modal_title')}
                description={t('ticket_modal_description')}
                iframeTitle={t('ticket_iframe_title')}
                printLabel={t('ticket_modal_print')}
            />
            <ConsultaCargosMain
                title={title}
                historiasUrl={historiasUrl}
                headerStats={headerStats}
                atendidoLabel={atendidoLabel}
                cargo={cargo}
                consulta={consultaStub}
                productosBuscarUrl={productosBuscarUrl}
                serviciosBuscarUrl={serviciosBuscarUrl}
                onSugerirDiasEstadia={
                    puedeEditar && noches_sugeridas && noches_sugeridas >= 1
                        ? sugerirNochesEstadia
                        : undefined
                }
                clinic_billing={clinic_billing}
                cobro={cobro}
                esBorrador={esBorrador}
                puedeEditar={puedeEditar}
                puedeSolicitarEditar={puedeSolicitarEditar}
                puedeEditarCargos={puedeEditarCargos}
                hayLineasGuardadas={hayLineasGuardadas}
                lineasSoloLectura={lineasSoloLectura}
                data={data}
                errors={errors}
                processing={processing}
                onSubmit={onSubmit}
                onEditar={entrarEnEdicion}
                addLinea={addLinea}
                removeLinea={removeLinea}
                updateLinea={updateLinea}
                setNotas={(value) => setData('notas', value)}
                abrirTicketEnModal={abrirTicketEnModal}
                formatMonto={formatMontoLocal}
                lineaImporteMostrar={lineaImporteMostrar}
                formatCantidadOnBlur={formatCantidadOnBlur}
                formatImporteOnBlur={formatImporteOnBlur}
                formatDecimal2={formatDecimal2}
            />
        </>
    );
}
