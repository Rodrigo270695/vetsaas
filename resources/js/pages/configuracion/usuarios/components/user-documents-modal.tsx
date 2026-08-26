import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { User } from '../types';

export type UserDocumentsModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
};

type DocumentsFormData = {
    colegiatura: string;
    cv: File | null;
    dni_file: File | null;
    firma: File | null;
    remove_cv: boolean;
    remove_dni_file: boolean;
    remove_firma: boolean;
};

const emptyForm: DocumentsFormData = {
    colegiatura: '',
    cv: null,
    dni_file: null,
    firma: null,
    remove_cv: false,
    remove_dni_file: false,
    remove_firma: false,
};

/**
 * Modal aparte: colegiatura + CV / escaneo DNI / firma.
 * Se abre desde el menú de acciones del listado (no en el alta).
 */
export function UserDocumentsModal({
    open,
    onOpenChange,
    user,
}: UserDocumentsModalProps) {
    const { t } = useTranslation(['usuarios', 'common']);
    const { data, setData, put, processing, errors, reset, clearErrors, transform } =
        useForm<DocumentsFormData>(emptyForm);

    const initialRef = useRef<DocumentsFormData>(emptyForm);

    useEffect(() => {
        if (open && user) {
            const initial: DocumentsFormData = {
                colegiatura: user.colegiatura ?? '',
                cv: null,
                dni_file: null,
                firma: null,
                remove_cv: false,
                remove_dni_file: false,
                remove_firma: false,
            };
            initialRef.current = initial;
            (Object.keys(initial) as Array<keyof DocumentsFormData>).forEach((key) => {
                setData(key, initial[key] as never);
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    const isDirty = useMemo(() => {
        const initial = initialRef.current;
        return (
            initial.colegiatura !== data.colegiatura ||
            data.cv !== null ||
            data.dni_file !== null ||
            data.firma !== null ||
            data.remove_cv ||
            data.remove_dni_file ||
            data.remove_firma
        );
    }, [data]);

    const handleClose = (next: boolean) => {
        if (!next) {
            if (isDirty && !window.confirm(t('common:form.unsaved_changes'))) {
                return;
            }
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!user) {
            return;
        }

        transform((form) => {
            const payload: Record<string, unknown> = { ...form };
            if (!form.cv) delete payload.cv;
            if (!form.dni_file) delete payload.dni_file;
            if (!form.firma) delete payload.firma;
            return payload;
        });

        put(`/configuracion/usuarios/${user.id}/documentos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                onOpenChange(false);
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={t('usuarios:documents.title', { name: user?.name ?? '' })}
            description={t('usuarios:documents.description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !user}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        )}
                        {t('usuarios:documents.submit')}
                    </Button>
                </>
            }
        >
            <FormSection
                index={0}
                title={t('usuarios:form.section_professional')}
                description={t('usuarios:form.section_professional_hint')}
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField
                        id="user-doc-colegiatura"
                        label={t('usuarios:form.fields.colegiatura')}
                        hint={t('usuarios:form.fields.colegiatura_hint')}
                        error={errors.colegiatura}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="user-doc-colegiatura"
                            value={data.colegiatura}
                            onChange={(e) => setData('colegiatura', e.target.value)}
                            placeholder={t('usuarios:form.fields.colegiatura_placeholder')}
                            autoComplete="off"
                        />
                    </FormField>

                    <FileField
                        id="user-doc-cv"
                        label={t('usuarios:form.fields.cv')}
                        hint={t('usuarios:form.fields.cv_hint')}
                        error={errors.cv}
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        existingUrl={!data.remove_cv ? (user?.cv_url ?? null) : null}
                        onFile={(file) => {
                            setData('cv', file);
                            setData('remove_cv', false);
                        }}
                        onClearExisting={() => {
                            setData('cv', null);
                            setData('remove_cv', true);
                        }}
                    />

                    <FileField
                        id="user-doc-dni"
                        label={t('usuarios:form.fields.dni_file')}
                        hint={t('usuarios:form.fields.dni_file_hint')}
                        error={errors.dni_file}
                        accept=".pdf,.jpg,.jpeg,.png"
                        existingUrl={
                            !data.remove_dni_file ? (user?.dni_file_url ?? null) : null
                        }
                        onFile={(file) => {
                            setData('dni_file', file);
                            setData('remove_dni_file', false);
                        }}
                        onClearExisting={() => {
                            setData('dni_file', null);
                            setData('remove_dni_file', true);
                        }}
                    />

                    <FileField
                        id="user-doc-firma"
                        label={t('usuarios:form.fields.firma')}
                        hint={t('usuarios:form.fields.firma_hint')}
                        error={errors.firma}
                        accept=".png,.jpg,.jpeg,.webp"
                        existingUrl={!data.remove_firma ? (user?.firma_url ?? null) : null}
                        onFile={(file) => {
                            setData('firma', file);
                            setData('remove_firma', false);
                        }}
                        onClearExisting={() => {
                            setData('firma', null);
                            setData('remove_firma', true);
                        }}
                        className="sm:col-span-2"
                    />
                </div>
            </FormSection>
        </FormModal>
    );
}

function FileField({
    id,
    label,
    hint,
    error,
    accept,
    existingUrl,
    onFile,
    onClearExisting,
    className,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    accept: string;
    existingUrl: string | null;
    onFile: (file: File | null) => void;
    onClearExisting: () => void;
    className?: string;
}) {
    const { t } = useTranslation('usuarios');

    return (
        <FormField id={id} label={label} hint={hint} error={error} className={className}>
            <div className="flex flex-col gap-2">
                <Input
                    id={id}
                    type="file"
                    accept={accept}
                    className="cursor-pointer"
                    onChange={(e) => {
                        const file = e.target.files?.[0] ?? null;
                        onFile(file);
                    }}
                />
                {existingUrl ? (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <a
                            href={existingUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary underline-offset-2 hover:underline"
                        >
                            {t('form.file_view_current')}
                        </a>
                        <button
                            type="button"
                            className="cursor-pointer text-destructive hover:underline"
                            onClick={onClearExisting}
                        >
                            {t('form.file_remove')}
                        </button>
                    </div>
                ) : null}
            </div>
        </FormField>
    );
}
