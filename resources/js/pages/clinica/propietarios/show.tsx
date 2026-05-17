import { Head, Link, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    Mail,
    MapPin,
    PawPrint,
    Pencil,
    Phone,
    Plus,
    Sparkles,
    UserCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { EmptyState, StatBadge } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { usePermission } from '@/hooks/use-permission';
import { isPropietarioDocumentTypeCode } from '@/lib/document-type-options';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import propietarios from '@/routes/clinica/propietarios';
import { PacienteDeleteDialog } from '../pacientes/components/paciente-delete-dialog';
import { PacienteFormModal } from '../pacientes/components/paciente-form-modal';
import { MascotaTarjetaFicha } from './components/mascota-tarjeta-ficha';
import { PropietarioFormModal } from './components/propietario-form-modal';
import type { GeoOption, Paciente, Propietario } from './types';

function displayNombre(p: Propietario): string {
    if (p.razon_social) {
        return p.razon_social;
    }
    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

function ubicacionLinea(p: Propietario): string | null {
    const parts = [p.departamento, p.provincia, p.distrito].filter(Boolean);
    return parts.length > 0 ? parts.join(' · ') : null;
}

function breadcrumbTitular(nombre: string): string {
    return nombre.length > 42 ? `${nombre.slice(0, 39)}…` : nombre;
}

type Props = {
    propietario: Propietario;
    pacientes: readonly Paciente[];
    departamentos: readonly GeoOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'edit-owner' }
    | { type: 'create-pet' }
    | { type: 'edit-pet'; paciente: Paciente }
    | { type: 'delete-pet'; paciente: Paciente };

type DatoProps = { label: string; children: ReactNode; className?: string };

function DatoCompacto({ label, children, className }: DatoProps) {
    return (
        <div className={cn('min-w-0', className)}>
            <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                {label}
            </p>
            <div className="mt-0.5 text-sm font-medium leading-snug text-foreground">{children}</div>
        </div>
    );
}

export default function Show({ propietario, pacientes, departamentos }: Props) {
    const { t } = useTranslation(['propietarios', 'pacientes', 'nav']);
    const { can } = usePermission();
    const canEditOwner = can('propietarios.update');
    const canCreatePet = can('pacientes.create');
    const canUpdatePet = can('pacientes.update');
    const canDeletePet = can('pacientes.delete');
    const canDownloadCarnetVacunas = can('vacunaciones.view');
    const canViewPetHistorial = can('pacientes.view');
    const canSeeAudit = can('audit-trail.view');
    const showPetActions =
        canUpdatePet || canDeletePet || canDownloadCarnetVacunas || canViewPetHistorial;

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const [ownerMoreOpen, setOwnerMoreOpen] = useState(false);
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const nombreTitular = useMemo(() => displayNombre(propietario), [propietario]);

    const title = useMemo(
        () => `${nombreTitular} · ${t('show.title_suffix')}`,
        [nombreTitular, t],
    );

    const docResumen = useMemo(() => {
        const num = propietario.numero_documento?.trim();
        if (!num) {
            return null;
        }
        const rawTipo = propietario.tipo_documento?.trim();
        let tipoEtiqueta = '';
        if (rawTipo) {
            const u = rawTipo.toUpperCase();
            if (isPropietarioDocumentTypeCode(u)) {
                tipoEtiqueta = `${t(`form.document_type_${u.toLowerCase()}`)} `;
            } else {
                tipoEtiqueta = `${rawTipo} `;
            }
        }
        return `${tipoEtiqueta}${num}`.trim();
    }, [propietario, t]);

    const ubicacion = ubicacionLinea(propietario);

    const contactoLinea = useMemo(() => {
        const bits: string[] = [];
        if (propietario.email?.trim()) {
            bits.push(propietario.email.trim());
        }
        const tels = [propietario.telefono, propietario.telefono_alt].filter(Boolean);
        if (tels.length) {
            bits.push(tels.join(' · '));
        }
        return bits.length ? bits.join(' · ') : null;
    }, [propietario]);

    const extraTitular = Boolean(propietario.direccion || ubicacion || propietario.notas);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('nav:groups.clinica'), href: dashboard().url },
                { title: t('title'), href: propietarios.index().url },
                { title: breadcrumbTitular(nombreTitular) },
            ],
        });
        return () => {
            resetLayoutProps();
        };
    }, [nombreTitular, t]);

    return (
        <>
            <Head title={title} />
            <div className="relative flex flex-1 flex-col gap-8 overflow-hidden p-4 sm:p-6">
                <div
                    className="pointer-events-none absolute inset-x-0 -top-24 h-72 opacity-[0.55] dark:opacity-35"
                    aria-hidden
                >
                    <div
                        className="mx-auto h-full max-w-5xl rounded-[100%] blur-3xl"
                        style={{
                            background:
                                'radial-gradient(ellipse at center, hsl(var(--primary) / 0.22), transparent 65%)',
                        }}
                    />
                </div>

                <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <Link
                            href={propietarios.index().url}
                            className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('show.back')}
                        </Link>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                    {nombreTitular}
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/25 bg-primary/5 font-normal text-primary"
                                >
                                    {t('show.badge_titular')}
                                </Badge>
                            </div>
                            <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                {t('description')}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                        {canEditOwner && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="cursor-pointer gap-2"
                                onClick={() => setModal({ type: 'edit-owner' })}
                            >
                                <Pencil className="size-4" strokeWidth={2.25} />
                                {t('show.edit_owner')}
                            </Button>
                        )}
                        <Can permission="pacientes.create">
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-2 shadow-sm"
                                onClick={() => setModal({ type: 'create-pet' })}
                            >
                                <Plus className="size-4" strokeWidth={2.25} />
                                {t('show.add_pet')}
                            </Button>
                        </Can>
                    </div>
                </div>

                <section
                    className="relative overflow-hidden rounded-2xl border border-border/60 bg-card/90 shadow-sm ring-1 ring-black/[0.03] backdrop-blur-sm dark:bg-card/80 dark:ring-white/[0.06]"
                    aria-labelledby="titular-heading"
                >
                    <div
                        className="pointer-events-none absolute -right-20 -top-20 size-56 rounded-full bg-gradient-to-br from-primary/15 to-transparent blur-2xl"
                        aria-hidden
                    />
                    <div className="relative flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:gap-5 sm:p-5">
                        <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 text-primary shadow-inner ring-1 ring-primary/10">
                            <UserCircle className="size-8" strokeWidth={1.75} />
                        </div>
                        <div className="min-w-0 flex-1 space-y-3">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2
                                    id="titular-heading"
                                    className="text-sm font-semibold tracking-tight text-foreground"
                                >
                                    {t('show.section_owner')}
                                </h2>
                                {propietario.activo ? (
                                    <StatBadge
                                        label={t('show.owner_status_label')}
                                        value={t('show.owner_active')}
                                        variant="success"
                                    />
                                ) : (
                                    <StatBadge
                                        label={t('show.owner_status_label')}
                                        value={t('show.owner_inactive')}
                                        variant="muted"
                                    />
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">{t('show.owner_compact_hint')}</p>

                            {propietario.razon_social ? (
                                <p className="rounded-lg border border-border/50 bg-muted/30 px-3 py-2 text-sm font-medium text-foreground">
                                    {propietario.razon_social}
                                </p>
                            ) : null}

                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <DatoCompacto label={t('form.nombres')}>
                                    {propietario.nombres?.trim() || '—'}
                                </DatoCompacto>
                                <DatoCompacto label={t('form.apellidos')}>
                                    {propietario.apellidos?.trim() || '—'}
                                </DatoCompacto>
                                <DatoCompacto label={t('show.label_doc')} className="sm:col-span-2">
                                    {docResumen ? (
                                        <span className="font-mono text-xs sm:text-sm">{docResumen}</span>
                                    ) : (
                                        <span className="font-normal text-muted-foreground">
                                            {t('row.no_doc')}
                                        </span>
                                    )}
                                </DatoCompacto>
                            </div>

                            {contactoLinea ? (
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border/50 pt-3 text-sm text-muted-foreground">
                                    {propietario.email?.trim() ? (
                                        <span className="inline-flex min-w-0 items-center gap-1.5">
                                            <Mail className="size-3.5 shrink-0 text-primary/80" aria-hidden />
                                            <span className="truncate text-foreground">{propietario.email}</span>
                                        </span>
                                    ) : null}
                                    {(propietario.telefono || propietario.telefono_alt) && (
                                        <span className="inline-flex items-center gap-1.5 tabular-nums">
                                            <Phone className="size-3.5 shrink-0 text-primary/80" aria-hidden />
                                            <span className="text-foreground">
                                                {[propietario.telefono, propietario.telefono_alt]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </span>
                                    )}
                                </div>
                            ) : null}

                            {extraTitular ? (
                                <Collapsible open={ownerMoreOpen} onOpenChange={setOwnerMoreOpen}>
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="-ml-2 h-8 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            <ChevronDown
                                                className={cn(
                                                    'size-4 transition-transform',
                                                    ownerMoreOpen && 'rotate-180',
                                                )}
                                            />
                                            {t('show.owner_more_toggle')}
                                        </Button>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="space-y-3 border-t border-border/40 pt-3 data-[state=closed]:animate-none">
                                        {propietario.direccion ? (
                                            <DatoCompacto label={t('show.label_address')}>
                                                {propietario.direccion}
                                            </DatoCompacto>
                                        ) : null}
                                        {ubicacion ? (
                                            <DatoCompacto label={t('show.label_location')}>
                                                <span className="inline-flex items-start gap-2">
                                                    <MapPin
                                                        className="mt-0.5 size-4 shrink-0 text-primary/80"
                                                        strokeWidth={2.25}
                                                    />
                                                    <span>{ubicacion}</span>
                                                </span>
                                            </DatoCompacto>
                                        ) : null}
                                        {propietario.notas ? (
                                            <DatoCompacto label={t('show.label_notes')}>
                                                <span className="whitespace-pre-wrap text-sm font-normal text-muted-foreground">
                                                    {propietario.notas}
                                                </span>
                                            </DatoCompacto>
                                        ) : null}
                                    </CollapsibleContent>
                                </Collapsible>
                            ) : null}
                        </div>
                    </div>
                </section>

                <section className="relative space-y-5" aria-labelledby="mascotas-heading">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2">
                                <span className="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15">
                                    <Sparkles className="size-4" strokeWidth={2} aria-hidden />
                                </span>
                                <h2
                                    id="mascotas-heading"
                                    className="text-lg font-semibold tracking-tight text-foreground sm:text-xl"
                                >
                                    {t('show.pets_deck_title')}
                                </h2>
                            </div>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                {t('show.pets_deck_hint')}
                            </p>
                        </div>
                    </div>

                    <div
                        className="rounded-2xl border border-dashed border-primary/15 bg-gradient-to-br from-primary/[0.04] via-background to-muted/40 p-4 sm:p-6 dark:from-primary/[0.07] dark:via-background dark:to-muted/20"
                    >
                        {pacientes.length === 0 ? (
                            <EmptyState
                                icon={PawPrint}
                                title={t('show.no_pets')}
                                description={t('show.no_pets_subtitle')}
                            />
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {pacientes.map((p) => (
                                    <li key={p.id} className="min-w-0 list-none">
                                        <MascotaTarjetaFicha
                                            paciente={p}
                                            canSeeAudit={canSeeAudit}
                                            showActions={showPetActions}
                                            canUpdatePet={canUpdatePet}
                                            canDeletePet={canDeletePet}
                                            canDownloadCarnetVacunas={canDownloadCarnetVacunas}
                                            carnetVacunasPdfUrl={
                                                canDownloadCarnetVacunas
                                                    ? clinica.pacientes.carnetVacunacionPdf.url({
                                                          paciente: p.id,
                                                      })
                                                    : undefined
                                            }
                                            canViewHistorial={canViewPetHistorial}
                                            onEdit={(x) => setModal({ type: 'edit-pet', paciente: x })}
                                            onDelete={(x) => setModal({ type: 'delete-pet', paciente: x })}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>

            <PropietarioFormModal
                open={modal.type === 'edit-owner'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                propietario={propietario}
                departamentos={departamentos}
            />

            <PacienteFormModal
                open={modal.type === 'create-pet' || modal.type === 'edit-pet'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                paciente={modal.type === 'edit-pet' ? modal.paciente : null}
                propietarioFijoId={propietario.id}
                propietariosOpciones={[]}
            />

            <PacienteDeleteDialog
                open={modal.type === 'delete-pet'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                paciente={modal.type === 'delete-pet' ? modal.paciente : null}
            />
        </>
    );
}
