import { useForm } from '@inertiajs/react';
import { FilePenLine, Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PlantillaAutorizacionOpcion = {
    id: string;
    nombre: string;
    descripcion: string | null;
};

type Props = {
    open: boolean;
    consultaId: string | null;
    plantillas: readonly PlantillaAutorizacionOpcion[];
    defaultPhone: string;
    defaultEmail: string;
    onOpenChange: (open: boolean) => void;
};

export function DocumentoAutorizacionSendDialog({
    open,
    consultaId,
    plantillas,
    defaultPhone,
    defaultEmail,
    onOpenChange,
}: Props) {
    const { t } = useTranslation('pacientes');
    const form = useForm({
        plantilla_id: plantillas[0]?.id ?? '',
        telefono: defaultPhone,
        email: defaultEmail,
        enviar_whatsapp: true,
        enviar_email: true,
    });

    useEffect(() => {
        if (open) {
            form.setData({
                plantilla_id: plantillas[0]?.id ?? '',
                telefono: defaultPhone,
                email: defaultEmail,
                enviar_whatsapp: true,
                enviar_email: true,
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, consultaId, defaultPhone, defaultEmail]);

    const submit = () => {
        if (!consultaId || !form.data.plantilla_id) {
            return;
        }
        form.post(`/clinica/historias-clinicas/consultas/${consultaId}/autorizacion`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FilePenLine className="size-4" />
                        {t('historial.autorizacion_title')}
                    </DialogTitle>
                    <DialogDescription>{t('historial.autorizacion_description')}</DialogDescription>
                </DialogHeader>
                {plantillas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('historial.autorizacion_sin_plantillas')}</p>
                ) : (
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label>{t('historial.autorizacion_plantilla')}</Label>
                            <Select
                                value={form.data.plantilla_id}
                                onValueChange={(v) => form.setData('plantilla_id', v)}
                            >
                                <SelectTrigger className="w-full cursor-pointer">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {plantillas.map((p) => (
                                        <SelectItem key={p.id} value={p.id} className="cursor-pointer">
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.plantilla_id} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="auth-phone">{t('historial.whatsapp_phone')}</Label>
                            <Input
                                id="auth-phone"
                                value={form.data.telefono}
                                onChange={(e) => form.setData('telefono', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="auth-email">{t('historial.autorizacion_email')}</Label>
                            <Input
                                id="auth-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.enviar_whatsapp}
                                onCheckedChange={(c) => form.setData('enviar_whatsapp', c === true)}
                            />
                            {t('historial.autorizacion_send_whatsapp')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.enviar_email}
                                onCheckedChange={(c) => form.setData('enviar_email', c === true)}
                            />
                            {t('historial.autorizacion_send_email')}
                        </label>
                    </div>
                )}
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        {t('historial.whatsapp_cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing || plantillas.length === 0}
                        onClick={submit}
                        className="gap-2"
                    >
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('historial.autorizacion_send')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
