import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Loader2, MessageCircle, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
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
import type { GroomingTurnoFoto, GroomingTurnoRow } from '../types';

type Props = {
    turno: GroomingTurnoRow;
    estadoActual: string;
};

function defaultTipo(estado: string): 'proceso' | 'final' {
    return estado === 'completada' ? 'final' : 'proceso';
}

export function GroomingFotosPanel({ turno, estadoActual }: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const fileRef = useRef<HTMLInputElement>(null);
    const [whatsappOpen, setWhatsappOpen] = useState(false);
    const defaultPhone = turno.paciente?.propietario?.telefono ?? '';

    const uploadForm = useForm<{
        foto: File | null;
        tipo: 'proceso' | 'final';
    }>({
        foto: null,
        tipo: defaultTipo(estadoActual),
    });

    const whatsappForm = useForm({
        telefono: defaultPhone,
        solo_pendientes: true as boolean,
    });

    useEffect(() => {
        uploadForm.setData('tipo', defaultTipo(estadoActual));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estadoActual]);

    useEffect(() => {
        whatsappForm.setData('telefono', defaultPhone);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [defaultPhone, turno.id]);

    const fotos = turno.fotos ?? [];
    const pendientes = useMemo(
        () => fotos.filter((f) => !f.enviado_whatsapp_at).length,
        [fotos],
    );

    const onPickFile = (file: File | null) => {
        if (!file) {
            return;
        }

        uploadForm.setData('foto', file);
        uploadForm.post(`/servicios/grooming/${turno.id}/fotos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.setData('foto', null);
                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
        });
    };

    const onDelete = (foto: GroomingTurnoFoto) => {
        router.delete(`/servicios/grooming/${turno.id}/fotos/${foto.id}`, {
            preserveScroll: true,
        });
    };

    const sendWhatsApp = () => {
        whatsappForm.post(`/servicios/grooming/${turno.id}/whatsapp-fotos`, {
            preserveScroll: true,
            onSuccess: () => setWhatsappOpen(false),
        });
    };

    return (
        <div className="space-y-3 rounded-lg border border-border/70 p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-medium">{t('fotos.title')}</p>
                    <p className="text-xs text-muted-foreground">{t('fotos.hint')}</p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="gap-1.5"
                    disabled={fotos.length === 0 || pendientes === 0 || whatsappForm.processing}
                    onClick={() => setWhatsappOpen(true)}
                >
                    <MessageCircle className="size-3.5" />
                    {t('fotos.send_whatsapp')}
                    {pendientes > 0 ? ` (${pendientes})` : ''}
                </Button>
            </div>

            {fotos.length > 0 ? (
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {fotos.map((foto) => (
                        <li key={foto.id} className="group relative overflow-hidden rounded-md border bg-muted/30">
                            <a href={foto.url ?? undefined} target="_blank" rel="noreferrer">
                                <img
                                    src={foto.url ?? ''}
                                    alt={foto.tipo}
                                    className="aspect-square w-full object-cover"
                                />
                            </a>
                            <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-1 bg-black/55 px-1.5 py-1 text-[10px] text-white">
                                <span>{t(`fotos.tipo.${foto.tipo}`)}</span>
                                {foto.enviado_whatsapp_at ? (
                                    <span>{t('fotos.sent')}</span>
                                ) : null}
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="destructive"
                                className="absolute top-1 right-1 size-7 opacity-90"
                                onClick={() => onDelete(foto)}
                                aria-label={t('fotos.delete')}
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-xs text-muted-foreground">{t('fotos.empty')}</p>
            )}

            <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[140px] flex-1 space-y-1">
                    <Label htmlFor={`gf-foto-tipo-${turno.id}`} className="text-xs">
                        {t('fotos.tipo_label')}
                    </Label>
                    <Select
                        value={uploadForm.data.tipo}
                        onValueChange={(v) => uploadForm.setData('tipo', v as 'proceso' | 'final')}
                        disabled={uploadForm.processing}
                    >
                        <SelectTrigger id={`gf-foto-tipo-${turno.id}`} className="h-9">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="proceso">{t('fotos.tipo.proceso')}</SelectItem>
                            <SelectItem value="final">{t('fotos.tipo.final')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex-1 space-y-1">
                    <Label htmlFor={`gf-foto-file-${turno.id}`} className="text-xs">
                        {t('fotos.upload')}
                    </Label>
                    <Input
                        id={`gf-foto-file-${turno.id}`}
                        ref={fileRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="h-9 cursor-pointer text-xs"
                        disabled={uploadForm.processing || fotos.length >= 8}
                        onChange={(e) => onPickFile(e.target.files?.[0] ?? null)}
                    />
                </div>
                {uploadForm.processing ? (
                    <Loader2 className="mb-2 size-4 animate-spin text-muted-foreground" />
                ) : (
                    <ImagePlus className="mb-2 size-4 text-muted-foreground" aria-hidden />
                )}
            </div>
            {uploadForm.errors.foto ? (
                <p className="text-xs text-destructive">{uploadForm.errors.foto}</p>
            ) : null}
            <p className="text-[11px] text-muted-foreground">{t('fotos.limits')}</p>

            <Dialog open={whatsappOpen} onOpenChange={setWhatsappOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('fotos.whatsapp_title')}</DialogTitle>
                        <DialogDescription>{t('fotos.whatsapp_description')}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2 py-1">
                        <Label htmlFor="grooming-wa-phone">{t('fotos.whatsapp_phone')}</Label>
                        <Input
                            id="grooming-wa-phone"
                            type="tel"
                            inputMode="tel"
                            placeholder="51999999999"
                            value={whatsappForm.data.telefono}
                            onChange={(e) => whatsappForm.setData('telefono', e.target.value)}
                        />
                        {whatsappForm.errors.telefono ? (
                            <p className="text-xs text-destructive">{whatsappForm.errors.telefono}</p>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setWhatsappOpen(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="gap-2"
                            disabled={whatsappForm.processing}
                            onClick={sendWhatsApp}
                        >
                            {whatsappForm.processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <MessageCircle className="size-4" />
                            )}
                            {t('fotos.whatsapp_submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
