import { Head, Link, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, PawPrint, Pencil, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { EmptyState } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import type { EspecieRazaCatalogo } from '@/lib/paciente-especie-raza-options';
import { isPropietarioDocumentTypeCode } from '@/lib/document-type-options';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import propietarios from '@/routes/clinica/propietarios';
import { PacienteDeleteDialog } from '../pacientes/components/paciente-delete-dialog';
import { PacienteFormModal } from '../pacientes/components/paciente-form-modal';
import { MascotaTarjetaFicha } from './components/mascota-tarjeta-ficha';
import { PropietarioFormModal } from './components/propietario-form-modal';
import { PropietarioTitularCard } from './components/propietario-titular-card';
import type { GeoOption, Paciente, Propietario } from './types';

function displayNombre(p: Propietario): string {
    if (p.razon_social) {
        return p.razon_social;
    }
    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

function breadcrumbTitular(nombre: string): string {
    return nombre.length > 42 ? `${nombre.slice(0, 39)}…` : nombre;
}

type Props = {
    propietario: Propietario;
    pacientes: readonly Paciente[];
    departamentos: readonly GeoOption[];
    especie_raza_catalogo: EspecieRazaCatalogo;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'edit-owner' }
    | { type: 'create-pet' }
    | { type: 'edit-pet'; paciente: Paciente }
    | { type: 'delete-pet'; paciente: Paciente };

export default function Show({
    propietario,
    pacientes,
    departamentos,
    especie_raza_catalogo,
}: Props) {
    const { t } = useTranslation(['propietarios', 'pacientes', 'nav']);
    const { can } = usePermission();
    const canEditOwner = can('propietarios.update');
    const canUpdatePet = can('pacientes.update');
    const canDeletePet = can('pacientes.delete');
    const canDownloadCarnetVacunas = can('vacunaciones.view');
    const canViewPetHistorial = can('pacientes.view');
    const canSeeAudit = can('audit-trail.view');
    const showPetActions =
        canUpdatePet || canDeletePet || canDownloadCarnetVacunas || canViewPetHistorial;

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
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
            <div className="relative flex flex-1 flex-col gap-8 p-4 sm:p-6">
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

                <div className="relative flex flex-col gap-3">
                    <Link
                        href={propietarios.index().url}
                        className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="size-4 shrink-0" strokeWidth={2.25} />
                        {t('show.back')}
                    </Link>
                    <PropietarioTitularCard
                        propietario={propietario}
                        displayName={nombreTitular}
                        docResumen={docResumen}
                        actions={
                            <>
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
                            </>
                        }
                    />
                </div>

                <section className="relative space-y-4" aria-labelledby="mascotas-heading">
                    <div className="flex items-baseline gap-2">
                        <h2
                            id="mascotas-heading"
                            className="text-lg font-semibold tracking-tight text-foreground"
                        >
                            {t('show.pets_deck_title')}
                        </h2>
                        <span className="text-sm tabular-nums text-muted-foreground">
                            {pacientes.length}
                        </span>
                    </div>

                    <div
                        className="rounded-2xl border border-dashed border-primary/15 bg-linear-to-br from-primary/4 via-background to-muted/40 p-4 sm:p-6 dark:from-primary/7 dark:via-background dark:to-muted/20"
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
                especieRazaCatalogo={especie_raza_catalogo}
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
