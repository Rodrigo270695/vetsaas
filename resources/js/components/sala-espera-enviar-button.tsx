import { Timer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
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
import { Textarea } from '@/components/ui/textarea';
import { SALA_ESPERA_CHANGED_EVENT } from '@/components/sala-espera-header-popover';
import {
    TIPO_ATENCION_SALA_CLASS,
    TIPO_ATENCION_SALA_DOT,
    TIPOS_ATENCION_SALA,
    type TipoAtencionSala,
} from '@/lib/sala-espera-atencion';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

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
    const [area, setArea] = useState<'consulta' | 'grooming'>(canConsulta ? 'consulta' : 'grooming');
    const [tipoAtencion, setTipoAtencion] = useState<TipoAtencionSala>('no_urgente');
    const [motivo, setMotivo] = useState('');

    useEffect(() => {
        if (usuariosProp) {
            setUsuarios([...usuariosProp]);
        }
    }, [usuariosProp]);

    useEffect(() => {
        if (!open) {
            setTratanteId(myId);
            setArea(canConsulta ? 'consulta' : 'grooming');
            setTipoAtencion('no_urgente');
            setMotivo('');
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
    }, [canConsulta, myId, open, usuariosProp]);

    if (!canConsulta && !canGrooming) {
        return null;
    }

    const send = async () => {
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
                    tipo: area,
                    tratante_id: tratanteId === '' ? null : tratanteId,
                    motivo: motivo.trim() === '' ? null : motivo.trim(),
                    tipo_atencion: tipoAtencion,
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
                <DialogContent className="max-w-md">
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
                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">{t('sala_espera.area')}</span>
                            <Select
                                value={area}
                                onValueChange={(value) => setArea(value as 'consulta' | 'grooming')}
                                disabled={busy}
                            >
                                <SelectTrigger className="h-10 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {canConsulta ? (
                                        <SelectItem value="consulta">{t('sala_espera.cita')}</SelectItem>
                                    ) : null}
                                    {canGrooming ? (
                                        <SelectItem value="grooming">{t('sala_espera.grooming')}</SelectItem>
                                    ) : null}
                                </SelectContent>
                            </Select>
                        </label>
                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">{t('sala_espera.tipo_atencion')}</span>
                            <Select
                                value={tipoAtencion}
                                onValueChange={(value) => setTipoAtencion(value as TipoAtencionSala)}
                                disabled={busy}
                            >
                                <SelectTrigger className={cn('h-10 w-full font-medium', TIPO_ATENCION_SALA_CLASS[tipoAtencion])}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TIPOS_ATENCION_SALA.map((opcion) => (
                                        <SelectItem key={opcion} value={opcion}>
                                            <span className="inline-flex items-center gap-2">
                                                <span className={cn('size-2.5 rounded-full', TIPO_ATENCION_SALA_DOT[opcion])} />
                                                {t(`sala_espera.tipos_atencion.${opcion}`)}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">{t('sala_espera.motivo_label')}</span>
                            <Textarea
                                value={motivo}
                                onChange={(event) => setMotivo(event.target.value)}
                                placeholder={t('sala_espera.motivo_placeholder')}
                                maxLength={500}
                                rows={3}
                                disabled={busy}
                            />
                        </label>
                    </div>
                    <DialogFooter>
                        <Button type="button" className="cursor-pointer" disabled={busy} onClick={() => void send()}>
                            {t('sala_espera.send_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
