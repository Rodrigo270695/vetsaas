import { Cake, Fingerprint, Palette, PawPrint, Scale } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { calcularEdadMascota, type EdadMascota } from '@/lib/edad-desde-fecha-nacimiento';
import { cn } from '@/lib/utils';
import { PacienteRowActions } from '../../pacientes/components/paciente-row-actions';
import type { AuditUser, Paciente } from '../types';

function sexoLabel(
    t: (k: string, o?: Record<string, string | number>) => string,
    sexo: string | null,
): string | null {
    if (!sexo) {
        return null;
    }
    const k = sexo.toLowerCase();
    if (k === 'm') {
        return t('pacientes:row.sexo_m');
    }
    if (k === 'h') {
        return t('pacientes:row.sexo_h');
    }
    if (k === 'u') {
        return t('pacientes:row.sexo_u');
    }
    return sexo;
}

function textoEdad(
    t: (k: string, o?: Record<string, string | number>) => string,
    edad: EdadMascota | null,
): string | null {
    if (!edad) {
        return null;
    }
    if (edad.menosDeUnMes) {
        return t('pacientes:card.edad_menos_un_mes');
    }
    const y = edad.years;
    const m = edad.months;
    if (y === 0) {
        return m === 1
            ? t('pacientes:card.edad_un_mes')
            : t('pacientes:card.edad_n_meses', { count: m });
    }
    if (m === 0) {
        return y === 1
            ? t('pacientes:card.edad_un_año')
            : t('pacientes:card.edad_n_años', { count: y });
    }
    const yStr =
        y === 1 ? t('pacientes:card.edad_un_año') : t('pacientes:card.edad_n_años', { count: y });
    const mStr =
        m === 1 ? t('pacientes:card.edad_un_mes') : t('pacientes:card.edad_n_meses', { count: m });
    return `${yStr} ${t('pacientes:card.edad_y')} ${mStr}`;
}

function fechaNacimientoLegible(fecha: string | null): string | null {
    if (!fecha?.trim()) {
        return null;
    }
    const d = fecha.slice(0, 10);
    if (d.length < 10) {
        return null;
    }
    const dt = new Date(`${d}T12:00:00`);
    if (Number.isNaN(dt.getTime())) {
        return null;
    }
    return dt.toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export type MascotaTarjetaFichaProps = {
    paciente: Paciente;
    canSeeAudit: boolean;
    showActions: boolean;
    canUpdatePet: boolean;
    canDeletePet: boolean;
    canDownloadCarnetVacunas?: boolean;
    carnetVacunasPdfUrl?: string;
    canViewHistorial?: boolean;
    onEdit: (p: Paciente) => void;
    onDelete: (p: Paciente) => void;
};

function AuditFoot({
    creadoPor,
    createdAt,
    t,
}: {
    creadoPor: AuditUser;
    createdAt: string;
    t: (k: string, o?: Record<string, string | number>) => string;
}) {
    if (!creadoPor) {
        return (
            <p className="text-[0.65rem] text-muted-foreground">
                {t('propietarios:row.system')} ·{' '}
                {new Date(createdAt).toLocaleDateString(undefined, {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                })}
            </p>
        );
    }
    return (
        <p className="text-[0.65rem] text-muted-foreground">
            <span className="font-medium text-foreground/80">{creadoPor.name}</span>
            {' · '}
            {new Date(createdAt).toLocaleDateString(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            })}
        </p>
    );
}

export function MascotaTarjetaFicha({
    paciente: p,
    canSeeAudit,
    showActions,
    canUpdatePet,
    canDeletePet,
    canDownloadCarnetVacunas = false,
    carnetVacunasPdfUrl,
    canViewHistorial = false,
    onEdit,
    onDelete,
}: MascotaTarjetaFichaProps) {
    const { t } = useTranslation(['pacientes', 'propietarios', 'common']);
    const [fotoOpen, setFotoOpen] = useState(false);
    const sexo = sexoLabel(t, p.sexo);
    const subline = [p.especie, p.raza].filter(Boolean).join(' · ');
    const tieneFoto = Boolean(p.foto_url);

    const edad = useMemo(() => calcularEdadMascota(p.fecha_nacimiento), [p.fecha_nacimiento]);
    const edadTexto = useMemo(() => textoEdad(t, edad), [t, edad]);
    const fechaNacTxt = useMemo(() => fechaNacimientoLegible(p.fecha_nacimiento), [p.fecha_nacimiento]);
    const tieneNacimiento = Boolean(fechaNacTxt || edadTexto);

    const pesoNum =
        p.peso_kg != null && String(p.peso_kg).trim() !== ''
            ? Number.parseFloat(String(p.peso_kg))
            : null;
    const pesoOk = pesoNum != null && !Number.isNaN(pesoNum);

    const esterilizadoEtiqueta = useMemo(() => {
        if (p.esterilizado === true) {
            return t('pacientes:card.esterilizado_si');
        }
        if (p.esterilizado === false) {
            return t('pacientes:card.esterilizado_no');
        }
        return t('pacientes:card.esterilizado_ns');
    }, [p.esterilizado, t]);

    return (
        <article className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.02] transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:ring-primary/10 dark:ring-white/5">
            <div className="relative aspect-[5/4] w-full shrink-0 overflow-hidden bg-gradient-to-br from-muted via-muted/80 to-primary/[0.07]">
                <div
                    className="pointer-events-none absolute inset-0 opacity-40"
                    style={{
                        backgroundImage: `radial-gradient(circle at 20% 20%, hsl(var(--primary) / 0.12), transparent 45%),
              radial-gradient(circle at 80% 60%, hsl(var(--primary) / 0.08), transparent 40%)`,
                    }}
                />
                {tieneFoto ? (
                    <>
                        <button
                            type="button"
                            className="relative z-[1] size-full cursor-zoom-in outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            onClick={() => setFotoOpen(true)}
                            title={t('pacientes:row.foto_ver_grande')}
                        >
                            <img
                                src={p.foto_url!}
                                alt=""
                                className="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            />
                            <span className="sr-only">
                                {t('pacientes:row.foto_ver_grande')}: {p.nombre}
                            </span>
                        </button>
                        <Dialog open={fotoOpen} onOpenChange={setFotoOpen}>
                            <DialogContent className="max-w-3xl sm:max-w-3xl">
                                <DialogHeader>
                                    <DialogTitle>{p.nombre}</DialogTitle>
                                    <DialogDescription className="sr-only">
                                        {t('pacientes:card.foto_dialog_desc')}
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="flex max-h-[min(70vh,560px)] justify-center overflow-auto rounded-md bg-muted/30 p-2">
                                    <img
                                        src={p.foto_url!}
                                        alt={p.nombre}
                                        className="max-h-full w-auto max-w-full object-contain"
                                    />
                                </div>
                            </DialogContent>
                        </Dialog>
                    </>
                ) : (
                    <div
                        className="relative z-[1] flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
                        aria-label={t('pacientes:row.foto_none')}
                    >
                        <span className="flex size-16 items-center justify-center rounded-2xl border border-dashed border-muted-foreground/30 bg-background/40 backdrop-blur-[2px]">
                            <PawPrint className="size-8 opacity-50" strokeWidth={1.75} />
                        </span>
                        <span className="text-xs font-medium tracking-wide text-muted-foreground/80">
                            {t('pacientes:row.foto_none')}
                        </span>
                    </div>
                )}
                {showActions && (
                    <div className="absolute right-2 top-2 z-[2] rounded-lg bg-background/85 p-0.5 shadow-sm ring-1 ring-border/60 backdrop-blur-sm">
                        <PacienteRowActions
                            paciente={p}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            canUpdate={canUpdatePet}
                            canDelete={canDeletePet}
                            canDownloadCarnetVacunas={canDownloadCarnetVacunas}
                            carnetVacunasPdfUrl={carnetVacunasPdfUrl}
                            canViewHistorial={canViewHistorial}
                        />
                    </div>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-3 p-4 pt-3">
                <div className="space-y-1">
                    <h3 className="text-lg font-semibold leading-tight tracking-tight text-foreground">
                        {p.nombre}
                    </h3>
                    {subline ? (
                        <p className="text-sm text-muted-foreground line-clamp-2">{subline}</p>
                    ) : null}
                    {sexo ? (
                        <p className="text-xs font-medium text-primary/90">{sexo}</p>
                    ) : null}
                </div>

                {(tieneNacimiento || pesoOk || p.color?.trim()) && (
                    <div className="space-y-2 rounded-xl border border-border/50 bg-muted/20 px-3 py-2.5 text-xs">
                        {tieneNacimiento ? (
                            <div className="flex gap-2">
                                <Cake
                                    className="mt-0.5 size-3.5 shrink-0 text-primary/85"
                                    strokeWidth={2.25}
                                    aria-hidden
                                />
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    {fechaNacTxt ? (
                                        <p className="text-muted-foreground">
                                            <span className="font-medium text-foreground/90">
                                                {t('pacientes:card.nacimiento_corta')}
                                            </span>{' '}
                                            <span className="text-foreground">{fechaNacTxt}</span>
                                        </p>
                                    ) : null}
                                    {edadTexto ? (
                                        <p className="font-medium text-primary">{edadTexto}</p>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}
                        {(pesoOk || p.color?.trim()) && (
                            <div
                                className={cn(
                                    'flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground',
                                    tieneNacimiento && 'border-t border-border/40 pt-2',
                                )}
                            >
                                {pesoOk ? (
                                    <span className="inline-flex items-center gap-1">
                                        <Scale className="size-3.5 text-primary/80" aria-hidden />
                                        <span className="font-medium text-foreground">
                                            {t('pacientes:card.peso_valor', {
                                                value: pesoNum.toLocaleString(undefined, {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 2,
                                                }),
                                            })}
                                        </span>
                                    </span>
                                ) : null}
                                {p.color?.trim() ? (
                                    <span className="inline-flex min-w-0 items-center gap-1">
                                        <Palette className="size-3.5 shrink-0 text-primary/80" aria-hidden />
                                        <span className="truncate text-foreground">{p.color.trim()}</span>
                                    </span>
                                ) : null}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap gap-1.5">
                    <Badge variant="outline" className="text-[0.65rem] font-normal">
                        {esterilizadoEtiqueta}
                    </Badge>
                </div>

                <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border/50 pt-3">
                    <div className="flex min-w-0 flex-1 items-center gap-1.5 text-muted-foreground">
                        <Fingerprint className="size-3.5 shrink-0 opacity-70" aria-hidden />
                        {p.microchip ? (
                            <span className="truncate font-mono text-xs text-foreground">{p.microchip}</span>
                        ) : (
                            <span className="text-xs">—</span>
                        )}
                    </div>
                    {p.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" />
                    )}
                </div>

                {canSeeAudit && (
                    <AuditFoot creadoPor={p.creado_por} createdAt={p.created_at} t={t} />
                )}
            </div>
        </article>
    );
}
