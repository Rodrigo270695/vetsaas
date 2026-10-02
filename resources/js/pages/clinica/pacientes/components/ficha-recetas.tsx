import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';
import { RecetaFormModal } from '@/pages/clinica/recetas/components/receta-form-modal';

export type FichaRecetaItem = {
    id: string;
    emitida_at: string | null;
    estado: string;
    medicamentos: string[];
};

type Props = {
    ns: 'antipulga' | 'desparasitacion';
    paciente: { id: string; nombre: string };
    registroId: string | null;
    origen: 'antipulga' | 'desparasitacion';
    recetasIniciales: FichaRecetaItem[];
    recargarUrl: string | null;
    puedeEditar: boolean;
};

export function FichaRecetas({
    ns,
    paciente,
    registroId,
    origen,
    recetasIniciales,
    recargarUrl,
    puedeEditar,
}: Props) {
    const { t } = useTranslation(['pacientes', 'recetas']);
    const { can } = usePermission();
    const [open, setOpen] = useState(false);
    const [recetas, setRecetas] = useState(recetasIniciales);
    const puedeCrear = puedeEditar && can('recetas.create');

    const refrescar = () => {
        if (!recargarUrl) {
            return;
        }

        void fetch(recargarUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((res) => res.json())
            .then((body: { registro?: { recetas?: FichaRecetaItem[] } | null }) => {
                setRecetas(body.registro?.recetas ?? []);
            })
            .catch(() => undefined);
    };

    return (
        <section className="rounded-xl border border-violet-200/70 bg-card px-3 py-2.5 shadow-sm dark:border-violet-900/40">
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">{t(`${ns}.receta`)}</h2>
                {puedeCrear ? (
                    <Button
                        type="button"
                        size="sm"
                        className="h-8 cursor-pointer"
                        onClick={() => {
                            if (!registroId) {
                                toastManager.add({
                                    type: 'info',
                                    title: t(`${ns}.receta_guardar`),
                                });
                                return;
                            }
                            setOpen(true);
                        }}
                    >
                        <Plus className="size-3.5" />
                        {t(`${ns}.receta_nueva`)}
                    </Button>
                ) : null}
            </div>
            {recetas.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">{t(`${ns}.receta_vacia`)}</p>
            ) : (
                <ul className="mt-2 space-y-1">
                    {recetas.map((receta) => (
                        <li key={receta.id} className="text-sm">
                            <span className="font-medium">
                                {receta.medicamentos.length > 0 ? receta.medicamentos.join(', ') : t(`${ns}.receta`)}
                            </span>
                            <span className="ml-2 text-xs text-muted-foreground">
                                {t(`recetas:estado.${receta.estado}`, { defaultValue: receta.estado })}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {registroId ? (
                <RecetaFormModal
                    open={open}
                    onOpenChange={setOpen}
                    receta={null}
                    prefillPacienteId={paciente.id}
                    pacientesOpciones={[{ id: paciente.id, nombre: paciente.nombre }]}
                    sedesOpciones={[]}
                    consultasOpciones={[]}
                    vinculo={
                        origen === 'antipulga'
                            ? { antipulgaId: registroId }
                            : { desparasitacionId: registroId }
                    }
                    onSaved={refrescar}
                />
            ) : null}
        </section>
    );
}
