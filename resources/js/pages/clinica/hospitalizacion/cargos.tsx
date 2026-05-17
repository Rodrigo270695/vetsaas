import { Head, resetLayoutProps, router, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { Loader2, Printer } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { ConsultaCargosMain } from '../historias-clinicas/components/consulta-cargos-main';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';

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

type InternamientoApi = {
    id: string;
    ingreso_at: string;
    motivo_ingreso: string;
    paciente: {
        id: string;
        nombre: string;
        propietario?: {
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        };
    };
    veterinario: { id: string; name: string } | null;
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

const LIST_URL = '/clinica/hospitalizacion';

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
    url_cargos_consulta: string | null;
};

type Props = {
    internamiento: InternamientoApi;
    cargo: CargoApi;
    dias_estadia: number;
    cobro: CobroApi;
    clinic_billing: {
        moneda: string;
        igv_porcentaje: number;
        precio_incluye_igv: boolean;
        ticket_ancho_mm: '58' | '80';
    };
};

export default function InternamientoCargos({
    internamiento,
    cargo,
    dias_estadia,
    cobro,
    clinic_billing,
}: Props) {
    const { t, i18n } = useTranslation(['consulta-cargos', 'hospitalizacion', 'nav', 'caja']);
    const { t: tCommon } = useTranslation('common');
    const [ticketModalOpen, setTicketModalOpen] = useState(false);
    const [ticketIframeBust, setTicketIframeBust] = useState(() => Date.now());
    const ticketIframeRef = useRef<HTMLIFrameElement>(null);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const puedeEditarCargos = can('consulta-cargos.manage') || can('hospitalizacion.update');
    const productosBuscarUrl = `/clinica/hospitalizacion/${internamiento.id}/cargos/productos-buscar`;
    const showUrl = `${LIST_URL}/${internamiento.id}`;
    const cargosBaseUrl = `${LIST_URL}/${internamiento.id}/cargos`;
    const esBorrador = cargo.estado === 'borrador';
    const puedeEditar = esBorrador && puedeEditarCargos;

    const initial = useMemo<FormState>(
        () => ({
            notas: cargo.notas ?? '',
            lineas:
                cargo.lineas.length > 0 ? mapLineasToForm(cargo.lineas) : puedeEditar ? [emptyLine()] : [],
        }),
        [cargo, puedeEditar],
    );

    const { data, setData, put, processing, errors, clearErrors } = useForm<FormState>(initial);

    const title = useMemo(
        () =>
            t('hospitalizacion:show.section_cobro') +
            ' · ' +
            internamiento.paciente.nombre,
        [internamiento.paciente.nombre, t],
    );

    const historiasUrl = showUrl;

    const ticketIframeSrc = useMemo(() => {
        const base = `${cargosBaseUrl}/ticket`;
        const sep = base.includes('?') ? '&' : '?';

        return `${base}${sep}_pv=${ticketIframeBust}`;
    }, [cargosBaseUrl, ticketIframeBust]);

    const abrirTicketEnModal = () => {
        setTicketIframeBust(Date.now());
        setTicketModalOpen(true);
    };

    const imprimirTicketDesdeIframe = () => {
        const win = ticketIframeRef.current?.contentWindow;
        if (win) {
            win.focus();
            win.print();
        }
    };

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('nav:groups.clinica'), href: dashboard().url },
                { title: t('hospitalizacion:title'), href: LIST_URL },
                { title: internamiento.paciente.nombre, href: showUrl },
                { title: t('breadcrumb_cargos'), href: '#' },
            ],
        });
        return () => {
            resetLayoutProps();
        };
    }, [historiasUrl, t]);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!puedeEditar) {
            return;
        }
        put(cargosBaseUrl, {
            preserveScroll: true,
            onSuccess: () => clearErrors(),
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

    const onConfirmar = () => {
        if (!puedeEditar) {
            return;
        }
        router.post(`${cargosBaseUrl}/confirmar`, undefined, {
            preserveScroll: true,
        });
    };

    const hayLineasGuardadas = cargo.lineas.length > 0;

    const sugerirDiasEstadia = () => {
        const concepto = t('hospitalizacion:cargos.linea_dia', { dias: dias_estadia });
        const nueva: LineaForm = {
            tipo_linea: 'servicio',
            concepto,
            cantidad: String(dias_estadia),
            precio_unitario: '0.00',
            descuento_importe: '0.00',
            producto_id: null,
            producto_label: null,
        };
        setData('lineas', [...data.lineas, nueva]);
    };

    const propietarioLabel = useMemo(() => {
        const pr = internamiento.paciente.propietario;
        if (!pr) {
            return '—';
        }

        return (
            pr.razon_social?.trim() ||
            [pr.nombres, pr.apellidos].filter(Boolean).join(' ').trim() ||
            '—'
        );
    }, [internamiento.paciente.propietario]);

    const atendidoLabel = formatAtendidoInAppTimezone(internamiento.ingreso_at, appLocale, appTz);

    const headerStats = useMemo(
        () => [
            {
                label: t('stat_paciente'),
                value: internamiento.paciente.nombre,
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
        [internamiento.paciente.nombre, esBorrador, propietarioLabel, t],
    );

    const consultaStub = useMemo(
        () => ({
            id: internamiento.id,
            veterinario: internamiento.veterinario,
        }),
        [internamiento.id, internamiento.veterinario],
    );

    const lineasSoloLectura = !puedeEditar && cargo.lineas.length > 0;

    const formatMontoLocal = useCallback(
        (amount: string | null, moneda: string) => formatMonto(amount, moneda, i18n.language),
        [i18n.language],
    );

    return (
        <>
            <Head title={title} />
            <Dialog open={ticketModalOpen} onOpenChange={setTicketModalOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-[calc(100%-1rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:p-6">
                    <DialogHeader className="shrink-0 space-y-1 pr-8 text-left">
                        <DialogTitle>{t('ticket_modal_title')}</DialogTitle>
                        <DialogDescription>{t('ticket_modal_description')}</DialogDescription>
                    </DialogHeader>
                    {ticketModalOpen ? (
                        <iframe
                            ref={ticketIframeRef}
                            title={t('ticket_iframe_title')}
                            src={ticketIframeSrc}
                            className="min-h-[50vh] w-full flex-1 rounded-md border border-border bg-white"
                        />
                    ) : null}
                    <DialogFooter className="shrink-0 gap-2 sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setTicketModalOpen(false)}
                        >
                            {tCommon('actions.close')}
                        </Button>
                        <Button type="button" className="gap-1.5" onClick={imprimirTicketDesdeIframe}>
                            <Printer className="size-4 shrink-0" aria-hidden />
                            {t('ticket_modal_print')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <ConsultaCargosMain
                title={title}
                historiasUrl={historiasUrl}
                headerStats={headerStats}
                atendidoLabel={atendidoLabel}
                cargo={cargo}
                consulta={consultaStub}
                productosBuscarUrl={productosBuscarUrl}
                onSugerirDiasEstadia={puedeEditar ? sugerirDiasEstadia : undefined}
                clinic_billing={clinic_billing}
                cobro={cobro}
                esBorrador={esBorrador}
                puedeEditar={puedeEditar}
                puedeEditarCargos={puedeEditarCargos}
                hayLineasGuardadas={hayLineasGuardadas}
                lineasSoloLectura={lineasSoloLectura}
                data={data}
                errors={errors}
                processing={processing}
                onSubmit={onSubmit}
                onConfirmar={onConfirmar}
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
