import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BedDouble,
    Bug,
    Cake,
    CalendarPlus,
    Cat,
    ChevronDown,
    Dog,
    ExternalLink,
    FileDown,
    FlaskConical,
    Hotel,
    MessageCircle,
    PawPrint,
    Pill,
    Plus,
    Scale,
    Scissors,
    ShieldCheck,
    Stethoscope,
    Syringe,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SalaEsperaEnviarButton } from '@/components/sala-espera-enviar-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermission } from '@/hooks/use-permission';
import { useTenantModuleEnabled } from '@/hooks/use-tenant-modules';
import { calcularEdadMascota } from '@/lib/edad-desde-fecha-nacimiento';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import clinica from '@/routes/clinica';

type Props = {
    paciente: Paciente;
    propietarioNombre: string;
    links: {
        nueva_consulta?: string;
        nueva_aplicacion?: string;
        historial_pdf: string | null;
        historial_whatsapp?: string | null;
        laboratorio_rapido?: string | null;
        nueva_desparasitacion?: string | null;
        nueva_antipulga?: string | null;
        petpass_registrar?: string | null;
        petpass_propietario?: string | null;
        petpass_perfil_publico?: string | null;
    };
    permisos: {
        consultas_crear: boolean;
        vacunas_crear: boolean;
        laboratorio_crear: boolean;
        citas_crear?: boolean;
        sala_espera_enviar?: boolean;
        petpass_register?: boolean;
    };
    timelineStats: {
        consultas: number;
        aplicaciones: number;
        servicios?: number;
        total: number;
    };
    hasTimeline: boolean;
    onShareHistory?: () => void;
    onNuevo?: (accion: HistorialNuevoAccion) => void;
    /** Vista pública para el titular: sin CTAs de administración. */
    variant?: 'admin' | 'public';
    clinicName?: string;
    expiresAt?: string | null;
    children?: ReactNode;
};

export type HistorialNuevoAccion =
    | 'consulta'
    | 'cita'
    | 'vacuna'
    | 'desparasitacion'
    | 'antipulga'
    | 'receta'
    | 'hospitalizacion'
    | 'cirugia'
    | 'grooming'
    | 'hotel'
    | 'archivo';

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
    onNuevo,
    variant = 'admin',
    clinicName,
    expiresAt,
    children,
}: Props) {
    const { t } = useTranslation(['pacientes']);
    const { can } = usePermission();
    const isPublic = variant === 'public';
    const citasModule = useTenantModuleEnabled('citas');
    const groomingModule = useTenantModuleEnabled('grooming');
    const hotelModule = useTenantModuleEnabled('hotel');
    const consultasModule = useTenantModuleEnabled('historias_clinicas');
    const vacunasModule = useTenantModuleEnabled('vacunaciones');
    const recetasModule = useTenantModuleEnabled('recetas');
    const cirugiasModule = useTenantModuleEnabled('cirugias');
    const hospitalModule = useTenantModuleEnabled('hospitalizacion');
    const laboratorioModule = useTenantModuleEnabled('laboratorio');
    const [nuevoOpen, setNuevoOpen] = useState(false);

    const nuevoAcciones = useMemo(() => {
        const items: { id: HistorialNuevoAccion; label: string; icon: LucideIcon }[] = [];

        if (consultasModule && permisos.consultas_crear) {
            items.push({
                id: 'consulta',
                label: t('historial.nuevo_consulta'),
                icon: Stethoscope,
            });
        }

        if (citasModule && permisos.citas_crear) {
            items.push({
                id: 'cita',
                label: t('historial.nuevo_cita'),
                icon: CalendarPlus,
            });
        }

        if (vacunasModule && permisos.vacunas_crear) {
            items.push({
                id: 'vacuna',
                label: t('historial.nuevo_vacuna'),
                icon: Syringe,
            });
        }

        if (links.nueva_desparasitacion) {
            items.push({
                id: 'desparasitacion',
                label: t('historial.nuevo_desparasitacion'),
                icon: Bug,
            });
        }

        if (links.nueva_antipulga) {
            items.push({
                id: 'antipulga',
                label: t('historial.nuevo_antipulga'),
                icon: Bug,
            });
        }

        if (recetasModule && can('recetas.create')) {
            items.push({
                id: 'receta',
                label: t('historial.nuevo_receta'),
                icon: Pill,
            });
        }

        if (hospitalModule && can('hospitalizacion.create')) {
            items.push({
                id: 'hospitalizacion',
                label: t('historial.nuevo_hospitalizacion'),
                icon: BedDouble,
            });
        }

        if (cirugiasModule && can('cirugias.create')) {
            items.push({
                id: 'cirugia',
                label: t('historial.nuevo_cirugia'),
                icon: Scissors,
            });
        }

        if (groomingModule && can('grooming.create')) {
            items.push({
                id: 'grooming',
                label: t('historial.nuevo_grooming'),
                icon: PawPrint,
            });
        }

        if (hotelModule && can('hotel.create')) {
            items.push({
                id: 'hotel',
                label: t('historial.nuevo_hotel'),
                icon: Hotel,
            });
        }

        if (laboratorioModule && permisos.laboratorio_crear && links.laboratorio_rapido) {
            items.push({
                id: 'archivo',
                label: t('historial.nuevo_archivo'),
                icon: FlaskConical,
            });
        }

        return items;
    }, [
        can,
        citasModule,
        consultasModule,
        cirugiasModule,
        groomingModule,
        hospitalModule,
        hotelModule,
        laboratorioModule,
        links.laboratorio_rapido,
        links.nueva_desparasitacion,
        links.nueva_antipulga,
        permisos.citas_crear,
        permisos.consultas_crear,
        permisos.laboratorio_crear,
        permisos.vacunas_crear,
        recetasModule,
        t,
        vacunasModule,
    ]);
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

            const data = (await res.json().catch(() => ({}))) as {
                message?: string;
                ok?: boolean;
                activate_url?: string;
                whatsapp_sent?: boolean;
            };

            if (!res.ok || !data.ok) {
                toastManager.error({
                    title: data.message || t('historial.petpass_start_error'),
                    duration: 8000,
                });
                return;
            }

            toastManager.success({
                title: data.message || t('historial.petpass_registered_pending'),
                description: data.whatsapp_sent
                    ? t('historial.petpass_whatsapp_sent')
                    : t('historial.petpass_whatsapp_skip'),
                duration: 8000,
            });

            window.location.reload();
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
                className="relative px-3 py-2.5 sm:px-4"
                style={{
                    backgroundImage: `linear-gradient(135deg, hsl(var(--primary) / 0.12) 0%, hsl(var(--primary) / 0.03) 48%, transparent 78%)`,
                }}
            >
                <div className="flex items-center gap-3">
                    <div className="relative size-12 shrink-0">
                        {paciente.foto_url ? (
                            <img
                                src={paciente.foto_url}
                                alt=""
                                className="size-full rounded-xl border border-background object-cover shadow-sm ring-1 ring-primary/15"
                            />
                        ) : (
                            <span className="flex size-full items-center justify-center rounded-xl border border-dashed border-primary/25 bg-background/70">
                                <SpeciesIcon especie={paciente.especie} className="size-5 text-primary/70" />
                            </span>
                        )}
                        <span
                            className={cn(
                                'absolute -right-0.5 -bottom-0.5 z-10 size-3 rounded-full border-2 border-background',
                                paciente.activo ? 'bg-emerald-500' : 'bg-red-500',
                            )}
                            title={paciente.activo ? t('historial.estado_activo') : t('historial.estado_inactivo')}
                            aria-label={paciente.activo ? t('historial.estado_activo') : t('historial.estado_inactivo')}
                        />
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h1 className="text-lg font-semibold tracking-tight text-foreground">{paciente.nombre}</h1>
                            {paciente.petpass_status === 'lost' ? (
                                <Badge className="border-red-500/30 bg-red-500/15 text-[0.65rem] font-semibold text-red-800 dark:text-red-200">
                                    {t('historial.petpass_badge_lost')}
                                </Badge>
                            ) : paciente.petpass_status === 'registered' ? (
                                <Badge className="border-cyan-500/30 bg-cyan-500/15 text-[0.65rem] font-semibold text-cyan-900 dark:text-cyan-100">
                                    {t('historial.petpass_badge_registered')}
                                </Badge>
                            ) : paciente.petpass_status === 'pending' ? (
                                <Badge variant="secondary" className="text-[0.65rem] font-medium">
                                    {t('historial.petpass_badge_pending')}
                                </Badge>
                            ) : paciente.microchip ? (
                                <Badge variant="outline" className="text-[0.65rem] font-medium text-muted-foreground">
                                    {t('historial.petpass_badge_local')}
                                </Badge>
                            ) : null}
                            {subline ? (
                                <span className="inline-flex items-center gap-1 text-sm text-foreground/80">
                                    <SpeciesIcon especie={paciente.especie} className="size-3.5 shrink-0 text-sky-600 dark:text-sky-400" />
                                    {subline}
                                </span>
                            ) : null}
                            {sexo ? (
                                <span className="inline-flex items-center rounded-full bg-violet-500/12 px-2 py-0.5 text-xs font-medium text-violet-800 dark:text-violet-200">
                                    {sexo}
                                </span>
                            ) : null}
                            {edadTexto ? (
                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/12 px-2 py-0.5 text-xs font-medium text-amber-900 dark:text-amber-100">
                                    <Cake className="size-3" />
                                    {edadTexto}
                                </span>
                            ) : null}
                            {pesoOk ? (
                                <span className="inline-flex items-center gap-1 rounded-full bg-sky-500/12 px-2 py-0.5 text-xs font-medium text-sky-900 dark:text-sky-100">
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
                                <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 font-mono text-[0.65rem] text-muted-foreground">
                                    {paciente.microchip}
                                </span>
                            ) : null}
                            <span className="inline-flex min-w-0 items-center gap-1 text-sm text-muted-foreground">
                                <UserRound className="size-3.5 shrink-0 text-primary/80" />
                                <span className="truncate">
                                    {t('historial.titular_label')}:{' '}
                                    <span className="font-medium text-foreground">{propietarioNombre}</span>
                                </span>
                            </span>
                        </div>
                    </div>

                    {!isPublic ? (
                        <Button type="button" variant="outline" size="sm" className="h-8 shrink-0 gap-1.5 px-2.5" asChild>
                            <Link href={clinica.pacientes.index().url} prefetch>
                                <ArrowLeft className="size-3.5" strokeWidth={2.25} />
                                <span className="hidden sm:inline">{t('historial.back_list')}</span>
                            </Link>
                        </Button>
                    ) : clinicName ? (
                        <div className="shrink-0 text-right">
                            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                {t('historial.public_clinic_label')}
                            </p>
                            <p className="text-sm font-semibold text-foreground">{clinicName}</p>
                        </div>
                    ) : null}
                </div>
                {isPublic && expiresAt ? (
                    <p className="mt-1.5 text-xs text-muted-foreground">{t('historial.public_expires_hint')}</p>
                ) : null}
            </div>

            <div className="flex flex-col gap-2 border-t border-border/40 px-3 py-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-4">
                <div className="flex flex-wrap gap-1.5">
                    {!isPublic && nuevoAcciones.length > 0 ? (
                        <DropdownMenu open={nuevoOpen} onOpenChange={setNuevoOpen}>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="group gap-2 shadow-sm transition-all duration-300 ease-out hover:shadow-md active:scale-[0.96] data-[state=open]:shadow-md"
                                >
                                    <Plus
                                        className="size-4 transition-transform duration-300 ease-out group-data-[state=open]:rotate-45"
                                        strokeWidth={2.25}
                                    />
                                    {t('historial.action_nuevo')}
                                    <ChevronDown
                                        className="size-3.5 opacity-80 transition-transform duration-300 ease-out group-data-[state=open]:rotate-180"
                                        strokeWidth={2.25}
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                className="w-56 origin-top duration-300 ease-out"
                            >
                                {nuevoAcciones.map((accion) => {
                                    const Icon = accion.icon;

                                    return (
                                        <DropdownMenuItem
                                            key={accion.id}
                                            className="cursor-pointer gap-2"
                                            onSelect={() => onNuevo?.(accion.id)}
                                        >
                                            <Icon className="size-4" strokeWidth={2.25} />
                                            {accion.label}
                                        </DropdownMenuItem>
                                    );
                                })}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                    {!isPublic && permisos.sala_espera_enviar ? (
                        <SalaEsperaEnviarButton
                            pacienteId={paciente.id}
                            canConsulta={citasModule}
                            canGrooming={groomingModule}
                        />
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
                    {!isPublic && links.petpass_perfil_publico ? (
                        <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                            <a href={links.petpass_perfil_publico} target="_blank" rel="noopener noreferrer">
                                <ExternalLink className="size-4" strokeWidth={2.25} />
                                {t('historial.action_petpass_public_profile')}
                            </a>
                        </Button>
                    ) : null}
                </div>

                {timelineStats.total > 0 ? (
                    <div className="flex flex-wrap gap-1.5 text-xs">
                        {timelineStats.consultas > 0 ? (
                            <span className="inline-flex items-center rounded-md border border-sky-500/25 bg-sky-500/8 px-2 py-0.5 font-medium text-sky-800 dark:text-sky-200">
                                {t('historial.stat_consultas', { count: timelineStats.consultas })}
                            </span>
                        ) : null}
                        {timelineStats.aplicaciones > 0 ? (
                            <span className="inline-flex items-center rounded-md border border-emerald-500/25 bg-emerald-500/8 px-2 py-0.5 font-medium text-emerald-800 dark:text-emerald-200">
                                {t('historial.stat_aplicaciones', { count: timelineStats.aplicaciones })}
                            </span>
                        ) : null}
                        {(timelineStats.servicios ?? 0) > 0 ? (
                            <span className="inline-flex items-center rounded-md border border-violet-500/25 bg-violet-500/8 px-2 py-0.5 font-medium text-violet-800 dark:text-violet-200">
                                {t('historial.stat_servicios', { count: timelineStats.servicios })}
                            </span>
                        ) : null}
                    </div>
                ) : null}
            </div>
            {children}
        </section>
    );
}
