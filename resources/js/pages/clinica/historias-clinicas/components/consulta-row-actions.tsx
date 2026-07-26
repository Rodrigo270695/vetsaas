import { router } from '@inertiajs/react';
import { Banknote, MoreHorizontal, Pencil, Syringe, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermission } from '@/hooks/use-permission';
import clinica from '@/routes/clinica';
import type { ConsultaHistoriaRow } from '../types';

export type ConsultaRowActionsProps = {
    consulta: ConsultaHistoriaRow;
    onEdit: (c: ConsultaHistoriaRow) => void;
    onDelete: (c: ConsultaHistoriaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCargosView: boolean;
};

export function ConsultaRowActions({
    consulta,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
    canCargosView,
}: ConsultaRowActionsProps) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const { can } = usePermission();
    const canVacunasCreate = can('vacunaciones.create');

    const pacienteId = consulta.historia_clinica.paciente?.id;

    const vacunasPrefillUrl =
        pacienteId != null
            ? clinica.vacunaciones.index.url({
                  query: {
                      prefill_paciente_id: pacienteId,
                      prefill_consulta_id: consulta.id,
                  },
              })
            : null;

    if (!canUpdate && !canDelete && !canVacunasCreate && !canCargosView) {
        return null;
    }

    const goCargos = () => {
        router.visit(clinica.historiasClinicas.consultas.cargos.show.url(consulta.id));
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 cursor-pointer text-muted-foreground"
                    aria-label={t('columns.acciones')}
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {canCargosView && (
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={goCargos}>
                        <Banknote className="size-4" strokeWidth={2.25} />
                        {t('actions.cargos_consulta')}
                    </DropdownMenuItem>
                )}
                {canVacunasCreate && !consulta.cerrada_at && vacunasPrefillUrl ? (
                    <DropdownMenuItem asChild className="cursor-pointer p-0">
                        <a
                            href={vacunasPrefillUrl}
                            className="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-sm"
                        >
                            <Syringe className="size-4" strokeWidth={2.25} />
                            {t('actions.registrar_aplicacion')}
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {canUpdate && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(consulta)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(consulta)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
