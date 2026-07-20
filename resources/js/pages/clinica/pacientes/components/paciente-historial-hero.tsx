import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Cake,
    Cat,
    Dog,
    ExternalLink,
    FileDown,
    FlaskConical,
    MessageCircle,
    PawPrint,
    Plus,
    Scale,
    ShieldCheck,
    Syringe,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { calcularEdadMascota } from '@/lib/edad-desde-fecha-nacimiento';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import clinica from '@/routes/clinica';
import type { Paciente } from '../../propietarios/types';

type Props = {
    paciente: Paciente;
    propietarioNombre: string;
    links: {
        nueva_consulta?: string;
        nueva_aplicacion?: string;
        historial_pdf: string | null;
        historial_whatsapp?: string | null;
        laboratorio_rapido?: string | null;
        petpass_registrar?: string | null;
        petpass_propietario?: string | null;
        petpass_certificado?: string | null;
    };
    permisos: {
        consultas_crear: boolean;
        vacunas_crear: boolean;
        laboratorio_crear: boolean;
        petpass_register?: boolean;
    };
    timelineStats: {
        consultas: number;
        aplicaciones: number;
        total: number;
    };
    hasTimeline: boolean;
    onShareHistory?: () => void;
    onOpenLaboratorio?: () => void;
    /** Vista pública para el titular: sin CTAs de administración. */
    variant?: 'admin' | 'public';
    clinicName?: string;
    expiresAt?: string | null;
};

function sexoLabel(t: (k: string) => string, sexo: string | null): string | null {
    if (!sexo) {
        return null;
    }

    const k = sexo.toLowerCase();

    if (k === 'm') {
        return t('row.sexo_m');
    }

    if (k === 'h') {
        return t('row.sexo_h');
    }

    if (k === 'u') {
        return t('row.sexo_u');
    }

    return sexo;
}

function textoEdad(
    t: (k: string, o?: Record<string, string | number>) => string,
    edad: ReturnType<typeof calcularEdadMascota>,
): string | null {
    if (!edad) {
        return null;
    }

    if (edad.menosDeUnMes) {
        return t('card.edad_menos_un_mes');
    }

    const y = edad.years;
    const m = edad.months;

    if (y === 0) {
        return m === 1 ? t('card.edad_un_mes') : t('card.edad_n_meses', { count: m });
    }

    if (m === 0) {
        return y === 1 ? t('card.edad_un_año') : t('card.edad_n_años', { count: y });
    }

    const yStr = y === 1 ? t('card.edad_un_año') : t('card.edad_n_años', { count: y });
    const mStr = m === 1 ? t('card.edad_un_mes') : t('card.edad_n_meses', { count: m });

    return `${yStr} ${t('card.edad_y')} ${mStr}`;
}

function SpeciesIcon({ especie, className }: { especie: string | null; className: string }) {
    const e = (especie ?? '').toLowerCase();

    if (e.includes('perro') || e.includes('canin') || e.includes('dog')) {
        return <Dog className={className} strokeWidth={1.75} />;
    }

    if (e.includes('gato') || e.includes('felin') || e.includes('cat')) {
        return <Cat className={className} strokeWidth={1.75} />;
    }

    return <PawPrint className={className} strokeWidth={1.75} />;
}

export function PacienteHistorialHero({
    paciente,
    propietarioNombre,
    links,
    permisos,
    timelineStats,
    hasTimeline,
    onShareHistory,
    onOpenLaboratorio,
    variant = 'admin',
    clinicName,
    expiresAt,
}: Props) {
    const { t } = useTranslation(['pacientes']);
    const isPublic = variant === 'public';
    const [petpassBusy, setPetpassBusy] = useState(false);
    const subline = [paciente.especie, paciente.raza].filter(Boolean).join(' · ');
    const sexo = sexoLabel(t, paciente.sexo);
    const edad = useMemo(() => calcularEdadMascota(paciente.fecha_nacimiento), [paciente.fecha_nacimiento]);
    const edadTexto = useMemo(() => textoEdad(t, edad), [t, edad]);
    const pesoNum =
        paciente.peso_kg != null && String(paciente.peso_kg).trim() !== ''
            ? Number.parseFloat(String(paciente.peso_kg))
            : null;
    const pesoOk = pesoNum != null && !Number.isNaN(pesoNum);

    const startPetPassRegistration = async () => {
        const url = links.petpass_registrar;
        if (!url || petpassBusy) {
            return;
        }

        setPetpassBusy(true);
        try {
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = (await res.json().catch(() => ({}))) as { message?: string; url?: string };

            if (!res.ok) {
                toastManager.error({
                    title: data.message || t('historial.petpass_start_error'),
                    duration: 8000,
                });
                return;
            }

            if (!data.url) {
                toastManager.error({
                    title: t('historial.petpass_start_error'),
                    duration: 8000,
                });
                return;
            }

            window.location.assign(data.url);
        } catch {
            toastManager.error({
                title: t('historial.petpass_start_error'),
                duration: 8000,
            });
        } finally {
            setPetpassBusy(false);
        }
    };


    return (
        <section className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] dark:ring-white/5">
            <div
                className="relative border-b border-border/50 px-4 py-5 sm:px-6"
                style={{
                    backgroundImage: `linear-gradient(135deg, hsl(var(--primary) / 0.14) 0%, hsl(var(--primary) / 0.04) 42%, transparent 72%),
                        radial-gradient(ellipse 80% 60% at 100% 0%, hsl(199 89% 48% / 0.12), transparent 55%),
                        radial-gradient(ellipse 60% 50% at 0% 100%, hsl(142 71% 45% / 0.08), transparent 50%)`,
                }}
            >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 gap-4">
                        <div className="relative shrink-0">
                            {paciente.foto_url ? (
                                <img
                                    src={paciente.foto_url}
                                    alt=""
                                    className="size-20 rounded-2xl border-2 border-background object-cover shadow-md ring-2 ring-primary/20 sm:size-24"
                                />
                            ) : (
                                <span className="flex size-20 items-center justify-center rounded-2xl border-2 border-dashed border-primary/25 bg-background/70 shadow-sm sm:size-24">
                                    <SpeciesIcon
                                        especie={paciente.especie}
                                        className="size-9 text-primary/70"
                                    />
                                </span>
                            )}
                            <span
                                className={cn(
                                    'absolute -bottom-1 -right-1 flex size-7 items-center justify-center rounded-full border-2 border-background shadow-sm',
                                    paciente.activo ? 'bg-emerald-500 text-white' : 'bg-muted text-muted-foreground',
                                )}
                                title={paciente.activo ? t('historial.estado_activo') : t('historial.estado_inactivo')}
                            >
                                <span className="size-2 rounded-full bg-current" />
                            </span>
                        </div>

                        <div className="min-w-0 flex-1 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                    {paciente.nombre}
                                </h1>
                                {paciente.petpass_status === 'lost' ? (
                                    <Badge className="border-red-500/30 bg-red-500/15 text-[0.7rem] font-semibold text-red-800 dark:text-red-200">
                                        {t('historial.petpass_badge_lost')}
                                    </Badge>
                                ) : paciente.petpass_status === 'registered' ? (
                                    <Badge className="border-cyan-500/30 bg-cyan-500/15 text-[0.7rem] font-semibold text-cyan-900 dark:text-cyan-100">
                                        {t('historial.petpass_badge_registered')}
                                    </Badge>
                                ) : paciente.petpass_status === 'pending' ? (
                                    <Badge variant="secondary" className="text-[0.7rem] font-medium">
                                        {t('historial.petpass_badge_pending')}
                                    </Badge>
                                ) : paciente.microchip ? (
                                    <Badge variant="outline" className="text-[0.7rem] font-medium text-muted-foreground">
                                        {t('historial.petpass_badge_local')}
                                    </Badge>
                                ) : null}
                                {timelineStats.total > 0 ? (
                                    <Badge
                                        variant="secondary"
                                        className="border-primary/20 bg-primary/10 text-[0.7rem] font-medium text-primary"
                                    >
                                        {t('historial.stat_total', { count: timelineStats.total })}
                                    </Badge>
                                ) : null}
                            </div>

                            {subline ? (
                                <p className="flex items-center gap-1.5 text-sm font-medium text-foreground/80">
                                    <SpeciesIcon
                                        especie={paciente.especie}
                                        className="size-4 shrink-0 text-sky-600 dark:text-sky-400"
                                    />
                                    {subline}
                                </p>
                            ) : null}

                            <div className="flex flex-wrap gap-1.5">
                                {sexo ? (
                                    <span className="inline-flex items-center rounded-full bg-violet-500/12 px-2.5 py-0.5 text-xs font-medium text-violet-800 dark:text-violet-200">
                                        {sexo}
                                    </span>
                                ) : null}
                                {edadTexto ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/12 px-2.5 py-0.5 text-xs font-medium text-amber-900 dark:text-amber-100">
                                        <Cake className="size-3" />
                                        {edadTexto}
                                    </span>
                                ) : null}
                                {pesoOk ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-sky-500/12 px-2.5 py-0.5 text-xs font-medium text-sky-900 dark:text-sky-100">
                                        <Scale className="size-3" />
                                        {t('card.peso_valor', {
                                            value: pesoNum.toLocaleString(undefined, {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 2,
                                            }),
                                        })}
                                    </span>
                                ) : null}
                                {paciente.microchip ? (
                                    <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 font-mono text-[0.65rem] text-muted-foreground">
                                        {paciente.microchip}
                                    </span>
                                ) : null}
                            </div>

                            <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                <UserRound className="size-3.5 shrink-0 text-primary/80" />
                                <span>
                                    {t('historial.titular_label')}:{' '}
                                    <span className="font-medium text-foreground">{propietarioNombre}</span>
                                </span>
                            </p>
                        </div>
                    </div>

                    {!isPublic ? (
                        <Button type="button" variant="outline" size="sm" className="shrink-0 gap-2 self-start" asChild>
                            <Link href={clinica.pacientes.index().url} prefetch>
                                <ArrowLeft className="size-4" strokeWidth={2.25} />
                                {t('historial.back_list')}
                            </Link>
                        </Button>
                    ) : clinicName ? (
                        <div className="shrink-0 rounded-xl border border-border/60 bg-background/80 px-3 py-2 text-right shadow-sm">
                            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                {t('historial.public_clinic_label')}
                            </p>
                            <p className="text-sm font-semibold text-foreground">{clinicName}</p>
                        </div>
                    ) : null}
                </div>
                {isPublic && expiresAt ? (
                    <p className="mt-3 text-xs text-muted-foreground">{t('historial.public_expires_hint')}</p>
                ) : null}
            </div>

            <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6">
                <div className="flex flex-wrap gap-2">
                    {!isPublic && permisos.consultas_crear && links.nueva_consulta ? (
                        <Button type="button" size="sm" className="gap-2 shadow-sm" asChild>
                            <a href={links.nueva_consulta}>
                                <Plus className="size-4" strokeWidth={2.25} />
                                {t('historial.action_nueva_consulta')}
                            </a>
                        </Button>
                    ) : null}
                    {!isPublic && permisos.vacunas_crear && links.nueva_aplicacion ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="gap-2 border border-emerald-500/25 bg-emerald-500/10 text-emerald-900 hover:bg-emerald-500/20 dark:text-emerald-100"
                            asChild
                        >
                            <a href={links.nueva_aplicacion}>
                                <Syringe className="size-4" strokeWidth={2.25} />
                                {t('historial.action_nueva_aplicacion')}
                            </a>
                        </Button>
                    ) : null}
                    {!isPublic && permisos.laboratorio_crear && links.laboratorio_rapido ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="gap-2 border-sky-500/30 text-sky-800 hover:bg-sky-500/10 dark:text-sky-200"
                            onClick={() => onOpenLaboratorio?.()}
                        >
                            <FlaskConical className="size-4" strokeWidth={2.25} />
                            {t('historial.action_laboratorio')}
                        </Button>
                    ) : null}
                    {links.historial_pdf && (isPublic || hasTimeline) ? (
                        <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                            <a href={links.historial_pdf} target="_blank" rel="noopener noreferrer">
                                <FileDown className="size-4" strokeWidth={2.25} />
                                {t('historial.action_historial_pdf')}
                            </a>
                        </Button>
                    ) : null}
                    {!isPublic && links.historial_whatsapp && hasTimeline && onShareHistory ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="gap-2 border-emerald-500/30 text-emerald-700 hover:bg-emerald-500/10 hover:text-emerald-800 dark:text-emerald-300"
                            onClick={onShareHistory}
                        >
                            <MessageCircle className="size-4" strokeWidth={2.25} />
                            {t('historial.action_whatsapp')}
                        </Button>
                    ) : null}
                    {!isPublic && links.petpass_registrar ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="gap-2 border-cyan-500/35 text-cyan-800 hover:bg-cyan-500/10 dark:text-cyan-200"
                            disabled={petpassBusy}
                            onClick={() => void startPetPassRegistration()}
                        >
                            <ShieldCheck className="size-4" strokeWidth={2.25} />
                            {petpassBusy
                                ? t('historial.action_petpass_registering')
                                : t('historial.action_petpass_register')}
                        </Button>
                    ) : null}
                    {!isPublic && links.petpass_propietario ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="gap-2 border-amber-500/40 text-amber-900 hover:bg-amber-500/10 dark:text-amber-100"
                            asChild
                        >
                            <a href={links.petpass_propietario}>
                                <ShieldCheck className="size-4" strokeWidth={2.25} />
                                {t('historial.action_petpass_needs_document')}
                            </a>
                        </Button>
                    ) : null}
                    {!isPublic && links.petpass_certificado ? (
                        <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                            <a href={links.petpass_certificado} target="_blank" rel="noopener noreferrer">
                                <ExternalLink className="size-4" strokeWidth={2.25} />
                                {t('historial.action_petpass_certificate')}
                            </a>
                        </Button>
                    ) : null}
                </div>

                {timelineStats.total > 0 ? (
                    <div className="flex flex-wrap gap-2 text-xs">
                        {timelineStats.consultas > 0 ? (
                            <span className="inline-flex items-center rounded-lg border border-sky-500/25 bg-sky-500/8 px-2.5 py-1 font-medium text-sky-800 dark:text-sky-200">
                                {t('historial.stat_consultas', { count: timelineStats.consultas })}
                            </span>
                        ) : null}
                        {timelineStats.aplicaciones > 0 ? (
                            <span className="inline-flex items-center rounded-lg border border-emerald-500/25 bg-emerald-500/8 px-2.5 py-1 font-medium text-emerald-800 dark:text-emerald-200">
                                {t('historial.stat_aplicaciones', { count: timelineStats.aplicaciones })}
                            </span>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
