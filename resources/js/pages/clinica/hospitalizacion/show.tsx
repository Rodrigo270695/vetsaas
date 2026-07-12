import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BedDouble,
    ClipboardPlus,
    MoreHorizontal,
    Pencil,
    Receipt,
    Trash2,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { DateText } from '@/components/ui/date-text';
import { EvolucionDeleteDialog } from './components/evolucion-delete-dialog';
import { EvolucionFormModal } from './components/evolucion-form-modal';
import type {
    InternamientoCobroInfo,
    InternamientoEvolucionRow,
    InternamientoShow,
    UsuarioHospitalizacionOpcion,
} from './types';

const LIST_URL = '/clinica/hospitalizacion';

type Props = {
    internamiento: InternamientoShow;
    usuarios_opciones: readonly UsuarioHospitalizacionOpcion[];
    cobro: InternamientoCobroInfo;
};

type EvoModal =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; evolucion: InternamientoEvolucionRow }
    | { type: 'delete'; evolucion: InternamientoEvolucionRow };

function displayPropietario(
    p: InternamientoShow['paciente']['propietario'],
): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

function vitalesResumen(e: InternamientoEvolucionRow): string {
    const parts: string[] = [];

    if (e.peso_kg != null && e.peso_kg !== '') {
        parts.push(`${e.peso_kg} kg`);
    }

    if (e.temperatura_c != null && e.temperatura_c !== '') {
        parts.push(`${e.temperatura_c} °C`);
    }

    if (e.fc_lpm != null) {
        parts.push(`FC ${e.fc_lpm}`);
    }

    if (e.fr_rpm != null) {
        parts.push(`FR ${e.fr_rpm}`);
    }

    return parts.length > 0 ? parts.join(' · ') : '—';
}

export default function Show({ internamiento, usuarios_opciones, cobro }: Props) {
    const { t } = useTranslation(['hospitalizacion', 'consulta-cargos', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canUpdate = can('hospitalizacion.update');

    const [evoModal, setEvoModal] = useState<EvoModal>({ type: 'idle' });
    const closeEvo = useCallback(() => setEvoModal({ type: 'idle' }), []);

    return (
        <>
            <Head title={`${t('title')} · ${internamiento.paciente.nombre}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <Button variant="ghost" size="sm" className="h-8 w-fit gap-1.5 px-2" asChild>
                            <Link href={LIST_URL}>
                                <ArrowLeft className="size-4" strokeWidth={2.25} />
                                {t('show.back')}
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <BedDouble className="size-6 text-primary" strokeWidth={2} />
                            <h1 className="text-xl font-semibold tracking-tight">
                                {internamiento.paciente.nombre}
                            </h1>
                            <Badge variant="outline" className="font-normal">
                                {t(`estado.${internamiento.estado}`, { defaultValue: internamiento.estado })}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {displayPropietario(internamiento.paciente.propietario)}
                            {internamiento.ubicacion ? ` · ${internamiento.ubicacion}` : ''}
                        </p>
                        <p className="text-sm font-medium text-foreground">{internamiento.motivo_ingreso}</p>
                    </div>
                    {canUpdate ? (
                        <Button
                            type="button"
                            className="cursor-pointer gap-2"
                            onClick={() => setEvoModal({ type: 'create' })}
                        >
                            <ClipboardPlus className="size-4" strokeWidth={2.5} />
                            {t('show.evoluciones_add')}
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-5 lg:grid-cols-3">
                    <div className="flex flex-col gap-5 lg:col-span-1">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">{t('show.section_resumen')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('columns.ingreso_at')}</p>
                                    <p className="font-medium">
                                        <DateText>{formatAtendidoInAppTimezone(
                                            internamiento.ingreso_at,
                                            appLocale,
                                            appTz,
                                        )}</DateText>
                                    </p>
                                </div>
                                {internamiento.alta_at ? (
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('columns.alta_at')}</p>
                                        <p className="font-medium">
                                            <DateText>{formatAtendidoInAppTimezone(
                                                internamiento.alta_at,
                                                appLocale,
                                                appTz,
                                            )}</DateText>
                                        </p>
                                    </div>
                                ) : null}
                                {internamiento.veterinario ? (
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('columns.veterinario')}</p>
                                        <p>{internamiento.veterinario.name}</p>
                                    </div>
                                ) : null}
                                {internamiento.sede ? (
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('columns.sede')}</p>
                                        <p>{internamiento.sede.nombre}</p>
                                    </div>
                                ) : null}
                                {internamiento.diagnostico_ingreso ? (
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('show.diagnostico')}</p>
                                        <p className="whitespace-pre-wrap">{internamiento.diagnostico_ingreso}</p>
                                    </div>
                                ) : null}
                                {internamiento.notas ? (
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('show.notas_internamiento')}</p>
                                        <p className="whitespace-pre-wrap">{internamiento.notas}</p>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Receipt className="size-4" strokeWidth={2.25} />
                                    {t('show.section_cobro')}
                                </CardTitle>
                                <CardDescription>{t('show.cobro_hint_sin_consulta')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {!cobro.cargo_internamiento && !cobro.cargo_consulta ? (
                                    <p className="text-sm text-muted-foreground">{t('show.cobro_sin_cargo')}</p>
                                ) : null}
                                {cobro.cargo_internamiento ? (
                                    <div className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t('show.cobro_ver_internamiento')}
                                        </p>
                                        <p>
                                            {t(`consulta-cargos:estado.${cobro.cargo_internamiento.estado}`, {
                                                defaultValue: cobro.cargo_internamiento.estado,
                                            })}{' '}
                                            · {cobro.cargo_internamiento.moneda} {cobro.cargo_internamiento.total}
                                        </p>
                                    </div>
                                ) : null}
                                {cobro.cargo_consulta ? (
                                    <div className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t('show.cobro_ver_consulta')}
                                        </p>
                                        <p>
                                            {t(`consulta-cargos:estado.${cobro.cargo_consulta.estado}`, {
                                                defaultValue: cobro.cargo_consulta.estado,
                                            })}{' '}
                                            · {cobro.cargo_consulta.moneda} {cobro.cargo_consulta.total}
                                        </p>
                                    </div>
                                ) : null}
                                {cobro.puede_gestionar_cargos && cobro.url_cargos_internamiento ? (
                                    <Button type="button" variant="default" className="w-full cursor-pointer" asChild>
                                        <a href={cobro.url_cargos_internamiento}>
                                            {t('show.cobro_ver_internamiento')}
                                        </a>
                                    </Button>
                                ) : null}
                                {cobro.url_cargos_consulta ? (
                                    <Button type="button" variant="outline" className="w-full cursor-pointer" asChild>
                                        <a href={cobro.url_cargos_consulta}>{t('show.cobro_ver_consulta')}</a>
                                    </Button>
                                ) : null}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base">{t('show.section_evoluciones')}</CardTitle>
                            <span className="text-xs text-muted-foreground">
                                {internamiento.evoluciones.length}
                            </span>
                        </CardHeader>
                        <CardContent>
                            {internamiento.evoluciones.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('show.evoluciones_empty')}
                                </p>
                            ) : (
                                <ul className="m-0 flex list-none flex-col gap-3 p-0">
                                    {internamiento.evoluciones.map((e) => (
                                        <li
                                            key={e.id}
                                            className="rounded-lg border border-border/60 bg-card px-3 py-3 shadow-sm"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        <DateText>{formatAtendidoInAppTimezone(
                                                            e.registrado_at,
                                                            appLocale,
                                                            appTz,
                                                        )}</DateText>
                                                        {e.veterinario ? ` · ${e.veterinario.name}` : ''}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {vitalesResumen(e)}
                                                    </p>
                                                    <p className="whitespace-pre-wrap text-sm text-foreground">
                                                        {e.evolucion}
                                                    </p>
                                                    {e.tratamiento ? (
                                                        <p className="whitespace-pre-wrap text-xs text-muted-foreground">
                                                            <span className="font-medium">
                                                                {t('evolucion.tratamiento')}:{' '}
                                                            </span>
                                                            {e.tratamiento}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                {canUpdate ? (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 shrink-0"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                className="cursor-pointer gap-2"
                                                                onClick={() =>
                                                                    setEvoModal({ type: 'edit', evolucion: e })
                                                                }
                                                            >
                                                                <Pencil className="size-4" />
                                                                {t('common:actions.edit')}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                className="cursor-pointer gap-2 text-destructive"
                                                                onClick={() =>
                                                                    setEvoModal({ type: 'delete', evolucion: e })
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                                {t('common:actions.delete')}
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                ) : null}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <EvolucionFormModal
                open={evoModal.type === 'create' || evoModal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEvo();
                    }
                }}
                internamientoId={internamiento.id}
                evolucion={evoModal.type === 'edit' ? evoModal.evolucion : null}
            />

            <EvolucionDeleteDialog
                open={evoModal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEvo();
                    }
                }}
                internamientoId={internamiento.id}
                evolucion={evoModal.type === 'delete' ? evoModal.evolucion : null}
            />
        </>
    );
}

Show.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Hospitalización', href: LIST_URL },
        { title: 'Detalle', href: '#' },
    ],
};
