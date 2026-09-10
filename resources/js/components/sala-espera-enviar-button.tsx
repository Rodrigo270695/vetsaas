import { Bath, Stethoscope, Timer } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import { toastManager } from '@/lib/toast';

type Props = {
    pacienteId: string;
    canConsulta: boolean;
    canGrooming: boolean;
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export function SalaEsperaEnviarButton({
    pacienteId,
    canConsulta,
    canGrooming,
}: Props) {
    const { t } = useTranslation('common');
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    if (!canConsulta && !canGrooming) {
        return null;
    }

    const send = async (tipo: 'consulta' | 'grooming') => {
        setBusy(true);
        try {
            const res = await fetch('/clinica/sala-espera/enviar', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ paciente_id: pacienteId, tipo }),
            });
            const json = (await res.json().catch(() => ({}))) as {
                created?: boolean;
                message?: string;
            };
            if (!res.ok) {
                toastManager.error({ title: t('sala_espera.send_error') });
                return;
            }
            toastManager.success({
                title:
                    json.created === false
                        ? t('sala_espera.send_exists')
                        : t('sala_espera.send_ok'),
            });
            window.dispatchEvent(new Event(SALA_ESPERA_CHANGED_EVENT));
            setOpen(false);
        } catch {
            toastManager.error({ title: t('sala_espera.send_error') });
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5 border-amber-500/35 px-2.5 text-amber-800 hover:bg-amber-500/10 dark:text-amber-200"
                onClick={() => setOpen(true)}
            >
                <Timer className="size-4" strokeWidth={2.25} />
                {t('sala_espera.action_short')}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{t('sala_espera.send_title')}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-2">
                        {canConsulta ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="h-20 cursor-pointer flex-col gap-1 border-sky-500/30"
                                disabled={busy}
                                onClick={() => void send('consulta')}
                            >
                                <Stethoscope className="size-6 text-sky-600" />
                                <span className="text-xs font-medium">
                                    {t('sala_espera.cita')}
                                </span>
                            </Button>
                        ) : null}
                        {canGrooming ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="h-20 cursor-pointer flex-col gap-1 border-violet-500/30"
                                disabled={busy}
                                onClick={() => void send('grooming')}
                            >
                                <Bath className="size-6 text-violet-600" />
                                <span className="text-xs font-medium">
                                    {t('sala_espera.grooming')}
                                </span>
                            </Button>
                        ) : null}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
