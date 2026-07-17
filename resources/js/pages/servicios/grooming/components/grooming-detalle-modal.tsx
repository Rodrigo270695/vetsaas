import { ImageOff, MessageCircle } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { FormModal } from '@/components/forms';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatAtendidoInAppTimezone } from '@/pages/clinica/historias-clinicas/format-atendido';
import type { GroomingTurnoFoto, GroomingTurnoRow } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    turno: GroomingTurnoRow | null;
};

function displayPropietario(
    p: GroomingTurnoRow['paciente'] extends infer P
        ? P extends { propietario?: infer O }
            ? O
            : null
        : null,
): string {
    if (!p) {
        return '—';
    }

    if ('razon_social' in p && p.razon_social) {
        return p.razon_social;
    }

    if ('nombres' in p) {
        return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
    }

    return '—';
}

function FotoCard({
    foto,
    tipoLabel,
    sentLabel,
}: {
    foto: GroomingTurnoFoto;
    tipoLabel: string;
    sentLabel: string;
}) {
    if (!foto.url) {
        return null;
    }

    return (
        <li className="overflow-hidden rounded-md border bg-muted/20">
            <a href={foto.url} target="_blank" rel="noreferrer" className="block">
                <img src={foto.url} alt={tipoLabel} className="aspect-square w-full object-cover" />
            </a>
            <div className="flex items-center justify-between gap-1 px-2 py-1.5 text-[11px] text-muted-foreground">
                <span>{tipoLabel}</span>
                {foto.enviado_whatsapp_at ? (
                    <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                        <MessageCircle className="size-3" />
                        {sentLabel}
                    </span>
                ) : null}
            </div>
        </li>
    );
}

export function GroomingDetalleModal({ open, onOpenChange, turno }: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;

    const fotos = turno?.fotos ?? [];
    const fotosProceso = useMemo(
        () => fotos.filter((f) => f.tipo === 'proceso'),
        [fotos],
    );
    const fotosFinal = useMemo(
        () => fotos.filter((f) => f.tipo === 'final'),
        [fotos],
    );

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('detalle.title')}
            description={t('detalle.description')}
            size="lg"
            footer={
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                    {t('common:actions.close')}
                </Button>
            }
        >
            {turno ? (
                <div className="grid gap-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.paciente')}</p>
                            <p className="text-sm font-medium">
                                {turno.paciente?.nombre ?? t('row.paciente_no_disponible')}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {displayPropietario(turno.paciente?.propietario)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.estado')}</p>
                            <Badge className="mt-0.5 text-[0.65rem] font-normal">
                                {t(`estado.${turno.estado}`, { defaultValue: turno.estado })}
                            </Badge>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.inicio_at')}</p>
                            <p className="text-sm">
                                {formatAtendidoInAppTimezone(turno.inicio_at, appLocale, appTz)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {turno.duracion_minutos} min
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.servicio')}</p>
                            <p className="text-sm">{turno.servicio_label ?? turno.servicio}</p>
                            {turno.servicio_detalle ? (
                                <p className="text-xs text-muted-foreground">{turno.servicio_detalle}</p>
                            ) : null}
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.responsable')}</p>
                            <p className="text-sm">{turno.responsable?.name ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">{t('columns.sede')}</p>
                            <p className="text-sm">{turno.sede?.nombre ?? '—'}</p>
                        </div>
                    </div>

                    {turno.paciente?.propietario?.telefono ? (
                        <p className="text-xs text-muted-foreground">
                            {t('detalle.telefono')}:{' '}
                            <span className="text-foreground">{turno.paciente.propietario.telefono}</span>
                        </p>
                    ) : null}

                    {turno.notas ? (
                        <div>
                            <p className="text-xs text-muted-foreground">{t('form.notas')}</p>
                            <p className="mt-0.5 whitespace-pre-wrap text-sm">{turno.notas}</p>
                        </div>
                    ) : null}

                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium">{t('detalle.fotos')}</p>
                            <span className="text-[11px] text-muted-foreground">
                                {fotos.length}
                            </span>
                        </div>

                        {fotos.length === 0 ? (
                            <div className="flex items-center gap-2 rounded-md border border-dashed px-3 py-6 text-xs text-muted-foreground">
                                <ImageOff className="size-4 shrink-0" />
                                {t('detalle.fotos_empty')}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {fotosProceso.length > 0 ? (
                                    <div className="space-y-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t('fotos.tipo.proceso')}
                                        </p>
                                        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {fotosProceso.map((foto) => (
                                                <FotoCard
                                                    key={foto.id}
                                                    foto={foto}
                                                    tipoLabel={t('fotos.tipo.proceso')}
                                                    sentLabel={t('fotos.sent')}
                                                />
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                                {fotosFinal.length > 0 ? (
                                    <div className="space-y-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t('fotos.tipo.final')}
                                        </p>
                                        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {fotosFinal.map((foto) => (
                                                <FotoCard
                                                    key={foto.id}
                                                    foto={foto}
                                                    tipoLabel={t('fotos.tipo.final')}
                                                    sentLabel={t('fotos.sent')}
                                                />
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                                {fotosProceso.length === 0 && fotosFinal.length === 0 ? (
                                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {fotos.map((foto) => (
                                            <FotoCard
                                                key={foto.id}
                                                foto={foto}
                                                tipoLabel={t(`fotos.tipo.${foto.tipo}`, {
                                                    defaultValue: foto.tipo,
                                                })}
                                                sentLabel={t('fotos.sent')}
                                            />
                                        ))}
                                    </ul>
                                ) : null}
                            </div>
                        )}
                    </div>
                </div>
            ) : null}
        </FormModal>
    );
}
