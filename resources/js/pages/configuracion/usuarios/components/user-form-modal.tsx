import { useForm } from '@inertiajs/react';
import { Loader2, Search, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toastManager } from '@/lib/toast';
import usuarios from '@/routes/configuracion/usuarios';
import type { User, UserRoleOption } from '../types';

export type UserFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
    rolesCatalog: readonly UserRoleOption[];
};

type UserFormData = {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    is_active: boolean;
    role: string;
    documento_tipo: string;
    documento_numero: string;
    colegiatura: string;
    cv: File | null;
    dni_file: File | null;
    firma: File | null;
    remove_cv: boolean;
    remove_dni_file: boolean;
    remove_firma: boolean;
};

const emptyForm: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    role: '',
    documento_tipo: '',
    documento_numero: '',
    colegiatura: '',
    cv: null,
    dni_file: null,
    firma: null,
    remove_cv: false,
    remove_dni_file: false,
    remove_firma: false,
};

const buildInitialData = (user: User | null): UserFormData => ({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    password: '',
    password_confirmation: '',
    is_active: user?.is_active ?? true,
    role: user?.roles[0]?.name ?? '',
    documento_tipo: user?.documento_tipo ?? '',
    documento_numero: user?.documento_numero ?? '',
    colegiatura: user?.colegiatura ?? '',
    cv: null,
    dni_file: null,
    firma: null,
    remove_cv: false,
    remove_dni_file: false,
    remove_firma: false,
});

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const isFormValid = (data: UserFormData, isEdit: boolean): boolean => {
    if (data.name.trim().length < 2) return false;
    if (!EMAIL_REGEX.test(data.email.trim())) return false;
    if (!data.role) return false;
    if (!isEdit) {
        if (data.password.length < 8) return false;
        if (data.password !== data.password_confirmation) return false;
    } else if (data.password.length > 0) {
        if (data.password.length < 8) return false;
        if (data.password !== data.password_confirmation) return false;
    }
    if (data.documento_tipo === 'DNI' && data.documento_numero !== '') {
        if (!/^[0-9]{8}$/.test(data.documento_numero)) return false;
    }
    return true;
};

/**
 * Modal de crear/editar usuario.
 * Documento de identidad (tipo/número) y adjuntos opcionales para cualquier rol.
 */
export function UserFormModal({
    open,
    onOpenChange,
    user,
    rolesCatalog,
}: UserFormModalProps) {
    const { t } = useTranslation(['usuarios', 'common']);
    const isEdit = user !== null;
    const [consultandoDni, setConsultandoDni] = useState(false);
    const lastDniConsultaRef = useRef<string | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } =
        useForm<UserFormData>(emptyForm);

    const canSubmit = isFormValid(data, isEdit) && !processing;

    const initialSnapshotRef = useRef<UserFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(user);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof UserFormData>).forEach((key) => {
                setData(key, initial[key] as never);
            });
            clearErrors();
            lastDniConsultaRef.current = null;
            setConsultandoDni(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return (
            initial.name !== data.name ||
            initial.email !== data.email ||
            initial.phone !== data.phone ||
            initial.is_active !== data.is_active ||
            initial.role !== data.role ||
            initial.documento_tipo !== data.documento_tipo ||
            initial.documento_numero !== data.documento_numero ||
            initial.colegiatura !== data.colegiatura ||
            data.password.length > 0 ||
            data.password_confirmation.length > 0 ||
            data.cv !== null ||
            data.dni_file !== null ||
            data.firma !== null ||
            data.remove_cv ||
            data.remove_dni_file ||
            data.remove_firma
        );
    }, [data]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) return true;
        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next) {
            if (!confirmDiscard()) {
                return;
            }
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const onConsultarDni = async () => {
        const dni = data.documento_numero.replace(/\D+/g, '');
        if (dni.length !== 8) {
            toastManager.error({ title: t('usuarios:form.consultar_invalid_dni') });
            return;
        }
        if (lastDniConsultaRef.current === dni) {
            return;
        }
        lastDniConsultaRef.current = dni;
        setConsultandoDni(true);
        try {
            const res = await fetch(
                `/configuracion/usuarios/consulta-dni?dni=${encodeURIComponent(dni)}`,
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            const body = (await res.json()) as {
                success?: boolean;
                message?: string;
                data?: { nombre_completo?: string; nombres?: string; apellidos?: string };
            };
            if (!res.ok || !body.success || !body.data) {
                toastManager.error({
                    title: body.message ?? t('usuarios:form.consultar_error'),
                });
                return;
            }
            const completo =
                body.data.nombre_completo?.trim() ||
                [body.data.nombres, body.data.apellidos].filter(Boolean).join(' ').trim();
            if (completo) {
                setData('name', completo);
                toastManager.success({ title: t('usuarios:form.consultar_ok') });
            }
        } catch {
            toastManager.error({ title: t('usuarios:form.consultar_error') });
        } finally {
            setConsultandoDni(false);
        }
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        transform((form) => {
            const payload: Record<string, unknown> = { ...form };
            if (!form.cv) delete payload.cv;
            if (!form.dni_file) delete payload.dni_file;
            if (!form.firma) delete payload.firma;
            return payload;
        });

        const opts = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess,
        };

        if (isEdit && user) {
            put(usuarios.update(user.id).url, opts);
        } else {
            post(usuarios.store().url, opts);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('usuarios:form.title_edit') : t('usuarios:form.title_create')}
            description={
                isEdit
                    ? t('usuarios:form.description_edit')
                    : t('usuarios:form.description_create')
            }
            size="xl"
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
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                        )}
                        {isEdit
                            ? t('usuarios:form.submit_edit')
                            : t('usuarios:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {errors.plan_limit ? (
                    <p
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {errors.plan_limit}
                    </p>
                ) : null}
                <FormSection
                    index={0}
                    title={t('usuarios:form.section_basic')}
                    description={t('usuarios:form.section_basic_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="user-name"
                            label={t('usuarios:form.fields.name')}
                            required
                            error={errors.name}
                        >
                            <Input
                                id="user-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('usuarios:form.fields.name_placeholder')}
                                autoComplete="off"
                                autoFocus
                            />
                        </FormField>

                        <FormField
                            id="user-email"
                            label={t('usuarios:form.fields.email')}
                            required
                            error={errors.email}
                        >
                            <Input
                                id="user-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder={t('usuarios:form.fields.email_placeholder')}
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="user-phone"
                            label={t('usuarios:form.fields.phone')}
                            error={errors.phone}
                        >
                            <Input
                                id="user-phone"
                                type="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                placeholder={t('usuarios:form.fields.phone_placeholder')}
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="user-role"
                            label={t('usuarios:form.fields.role')}
                            required
                            error={errors.role}
                        >
                            <Select
                                value={data.role}
                                onValueChange={(value) => setData('role', value)}
                            >
                                <SelectTrigger id="user-role" className="w-full">
                                    <SelectValue
                                        placeholder={t('usuarios:form.fields.role_placeholder')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {rolesCatalog.map((role) => (
                                        <SelectItem
                                            key={role.id}
                                            value={role.name}
                                            className="cursor-pointer"
                                        >
                                            <div className="flex items-center gap-2">
                                                <ShieldCheck
                                                    className={
                                                        role.is_system
                                                            ? 'size-3.5 text-amber-600 dark:text-amber-400'
                                                            : 'size-3.5 text-primary/80'
                                                    }
                                                    strokeWidth={2.5}
                                                />
                                                <span className="font-mono text-xs">
                                                    {role.name}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="user-documento-tipo"
                            label={t('usuarios:form.fields.documento_tipo')}
                            error={errors.documento_tipo}
                        >
                            <Select
                                value={data.documento_tipo || undefined}
                                onValueChange={(value) => {
                                    lastDniConsultaRef.current = null;
                                    let numero = data.documento_numero;
                                    if (value === 'DNI') {
                                        numero = numero.replace(/\D+/g, '').slice(0, 8);
                                    }
                                    setData({
                                        ...data,
                                        documento_tipo: value,
                                        documento_numero: numero,
                                    });
                                }}
                            >
                                <SelectTrigger id="user-documento-tipo" className="w-full">
                                    <SelectValue
                                        placeholder={t(
                                            'usuarios:form.fields.documento_tipo_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="DNI">DNI</SelectItem>
                                    <SelectItem value="CE">CE</SelectItem>
                                    <SelectItem value="PAS">Pasaporte</SelectItem>
                                    <SelectItem value="OTRO">Otro</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="user-documento-numero"
                            label={t('usuarios:form.fields.documento_numero')}
                            error={errors.documento_numero}
                            hint={
                                data.documento_tipo === 'DNI'
                                    ? t('usuarios:form.fields.documento_numero_dni_hint')
                                    : undefined
                            }
                        >
                            <div className="flex gap-2">
                                <Input
                                    id="user-documento-numero"
                                    value={data.documento_numero}
                                    onChange={(e) => {
                                        lastDniConsultaRef.current = null;
                                        const raw = e.target.value;
                                        setData(
                                            'documento_numero',
                                            data.documento_tipo === 'DNI'
                                                ? raw.replace(/\D+/g, '').slice(0, 8)
                                                : raw.slice(0, 32),
                                        );
                                    }}
                                    placeholder={
                                        data.documento_tipo === 'DNI'
                                            ? '12345678'
                                            : t(
                                                  'usuarios:form.fields.documento_numero_placeholder',
                                              )
                                    }
                                    inputMode={
                                        data.documento_tipo === 'DNI' ? 'numeric' : 'text'
                                    }
                                    autoComplete="off"
                                />
                                {data.documento_tipo === 'DNI' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="shrink-0 cursor-pointer gap-1.5"
                                        disabled={
                                            consultandoDni ||
                                            data.documento_numero.length !== 8
                                        }
                                        onClick={() => void onConsultarDni()}
                                    >
                                        {consultandoDni ? (
                                            <Loader2
                                                className="size-4 animate-spin"
                                                aria-hidden
                                            />
                                        ) : (
                                            <Search className="size-4" aria-hidden />
                                        )}
                                        {t('usuarios:form.consultar_dni')}
                                    </Button>
                                ) : null}
                            </div>
                        </FormField>

                        <FormField
                            id="user-colegiatura"
                            label={t('usuarios:form.fields.colegiatura')}
                            error={errors.colegiatura}
                            hint={t('usuarios:form.fields.colegiatura_hint')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="user-colegiatura"
                                value={data.colegiatura}
                                onChange={(e) => setData('colegiatura', e.target.value)}
                                placeholder={t(
                                    'usuarios:form.fields.colegiatura_placeholder',
                                )}
                                autoComplete="off"
                            />
                        </FormField>

                        <FileField
                            id="user-cv"
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
                            id="user-dni-file"
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
                            id="user-firma"
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

                        <FormField
                            id="user-is-active"
                            label={t('usuarios:form.fields.is_active')}
                            hint={t('usuarios:form.fields.is_active_hint')}
                            error={errors.is_active}
                            className="sm:col-span-2"
                        >
                            <label
                                htmlFor="user-is-active"
                                className="flex h-9 cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <Checkbox
                                    id="user-is-active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <span className="text-foreground/80">
                                    {data.is_active
                                        ? t('usuarios:row.active')
                                        : t('usuarios:row.suspended')}
                                </span>
                            </label>
                        </FormField>
                    </div>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('usuarios:form.section_access')}
                    description={
                        isEdit
                            ? t('usuarios:form.section_access_hint_edit')
                            : t('usuarios:form.section_access_hint_create')
                    }
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="user-password"
                            label={t('usuarios:form.fields.password')}
                            required={!isEdit}
                            error={errors.password}
                        >
                            <PasswordInput
                                id="user-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder={
                                    isEdit
                                        ? t('usuarios:form.fields.password_placeholder_edit')
                                        : t('usuarios:form.fields.password_placeholder')
                                }
                                autoComplete="new-password"
                            />
                        </FormField>

                        <FormField
                            id="user-password-confirmation"
                            label={t('usuarios:form.fields.password_confirmation')}
                            required={!isEdit && data.password.length > 0}
                        >
                            <PasswordInput
                                id="user-password-confirmation"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                                placeholder={
                                    isEdit
                                        ? t('usuarios:form.fields.password_placeholder_edit')
                                        : t('usuarios:form.fields.password_placeholder')
                                }
                                autoComplete="new-password"
                            />
                        </FormField>
                    </div>
                </FormSection>
            </div>
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
