import { Bath, Stethoscope, Timer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import { toastManager } from '@/lib/toast';

type UsuarioSala = {
    id: string;
    name: string;
};

type Props = {
    pacienteId: string;
    canConsulta: boolean;
    canGrooming: boolean;
    usuarios?: readonly UsuarioSala[];
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
    usuarios: usuariosProp,
}: Props) {
    const { t } = useTranslation('common');
    const { auth } = usePage().props;
    const myId = auth.user?.id ? String(auth.user.id) : '';
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [usuarios, setUsuarios] = useState<UsuarioSala[]>(() => [...(usuariosProp ?? [])]);
    const [tratanteId, setTratanteId] = useState<string>(myId);

    useEffect(() => {
        if (usuariosProp) {
            setUsuarios([...usuariosProp]);
        }
    }, [usuariosProp]);

    useEffect(() => {
        if (!open) {
            setTratanteId(myId);
            return;
        }
        if (usuariosProp && usuariosProp.length > 0) {
            return;
        }
        void fetch('/clinica/sala-espera/usuarios', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    return;
                }
                const json = (await res.json()) as { data?: UsuarioSala[] };
                setUsuarios(Array.isArray(json.data) ? json.data : []);
            })
            .catch(() => {
                // El envío sigue con el usuario actual.
            });
    }, [myId, open, usuariosProp]);

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
                body: JSON.stringify({
                    paciente_id: pacienteId,
                    tipo,
                    tratante_id: tratanteId === '' ? null : tratanteId,
                }),
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
                    <div className="grid gap-3">
                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">{t('sala_espera.tratante')}</span>
                            <Select
                                value={tratanteId === '' ? '__none__' : tratanteId}
                                onValueChange={(value) => setTratanteId(value === '__none__' ? '' : value)}
                                disabled={busy}
                            >
                                <SelectTrigger className="h-10 w-full">
                                    <SelectValue placeholder={t('sala_espera.tratante_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">{t('sala_espera.tratante_placeholder')}</SelectItem>
                                    {usuarios.map((usuario) => (
                                        <SelectItem key={usuario.id} value={usuario.id}>
                                            {usuario.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <span className="text-xs text-muted-foreground">{t('sala_espera.tratante_hint')}</span>
                        </label>
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
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
