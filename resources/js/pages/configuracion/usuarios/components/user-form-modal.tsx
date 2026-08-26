import { useForm } from '@inertiajs/react';
import { Loader2, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DocumentNumberLookupField,
    DocumentTypeSelect,
    FormField,
    FormModal,
    FormSection,
    STAFF_DOCUMENT_TYPE_CODES,
    isStaffDocumentTypeCode,
    soloDigitosDocumento,
} from '@/components/forms';
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
};

const emptyForm: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    role: '',
    documento_tipo: 'DNI',
    documento_numero: '',
};

const normalizeDocumentoTipo = (raw: string | null | undefined): string => {
    if (!raw) {
        return 'DNI';
    }
    const u = raw.trim().toUpperCase();
    if (u === 'OTR') {
        return 'OTRO';
    }
    return isStaffDocumentTypeCode(u) ? u : 'DNI';
};

const buildInitialData = (user: User | null): UserFormData => ({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    password: '',
    password_confirmation: '',
    is_active: user?.is_active ?? true,
    role: user?.roles[0]?.name ?? '',
    documento_tipo: user ? normalizeDocumentoTipo(user.documento_tipo) : 'DNI',
    documento_numero: user?.documento_numero ?? '',
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
 * Modal crear/editar usuario (datos de cuenta).
 * Documento arriba + RENIEC auto como propietarios.
 * CV / firma / colegiatura → modal aparte (`UserDocumentsModal`).
 */
export function UserFormModal({
    open,
    onOpenChange,
    user,
    rolesCatalog,
}: UserFormModalProps) {
    const { t } = useTranslation(['usuarios', 'propietarios', 'common']);
    const isEdit = user !== null;
    const [consultandoDoc, setConsultandoDoc] = useState(false);
    const lastConsultaKeyRef = useRef<string | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<UserFormData>(emptyForm);

    const canSubmit = isFormValid(data, isEdit) && !processing;
    const initialSnapshotRef = useRef<UserFormData>(emptyForm);

    const tipoDoc = data.documento_tipo.trim().toUpperCase();
    const isDni = tipoDoc === 'DNI';
    const docMaxLen = isDni ? 8 : undefined;
    const docCompleto =
        docMaxLen !== undefined &&
        soloDigitosDocumento(data.documento_numero).length === docMaxLen;

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(user);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof UserFormData>).forEach((key) => {
                setData(key, initial[key] as never);
            });
            clearErrors();
            setConsultandoDoc(false);
            const tipo = initial.documento_tipo.trim().toUpperCase();
            const max = tipo === 'DNI' ? 8 : undefined;
            const digits = soloDigitosDocumento(initial.documento_numero, max);
            lastConsultaKeyRef.current =
                max !== undefined && digits.length === max ? `${tipo}:${digits}` : null;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    const onConsultarDni = async (forcedNumero?: string) => {
        const dni = soloDigitosDocumento(forcedNumero ?? data.documento_numero, 8);
        if (dni.length !== 8) {
            toastManager.error({ title: t('usuarios:form.consultar_invalid_dni') });
            return;
        }
        const key = `DNI:${dni}`;
        lastConsultaKeyRef.current = key;
        setConsultandoDoc(true);
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
                data?: {
                    nombre_completo?: string;
                    nombres?: string;
                    apellidos?: string;
                    dni?: string;
                };
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
            setData((prev) => ({
                ...prev,
                documento_numero: body.data?.dni ?? dni,
                name: completo || prev.name,
            }));
            if (completo) {
                toastManager.success({ title: t('usuarios:form.consultar_ok') });
            }
        } catch {
            toastManager.error({ title: t('usuarios:form.consultar_error') });
        } finally {
            setConsultandoDoc(false);
        }
    };

    useEffect(() => {
        if (!open || !isDni || !docCompleto || consultandoDoc || processing) {
            return;
        }
        const digits = soloDigitosDocumento(data.documento_numero, 8);
        const key = `DNI:${digits}`;
        if (lastConsultaKeyRef.current === key) {
            return;
        }
        void onConsultarDni(digits);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, data.documento_numero, tipoDoc, docCompleto, consultandoDoc, processing]);

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
            data.password.length > 0 ||
            data.password_confirmation.length > 0
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

    const handleTipoChange = (value: string) => {
        const upper = value.trim().toUpperCase();
        let numero = data.documento_numero;
        if (upper === 'DNI') {
            numero = soloDigitosDocumento(numero, 8);
        }
        lastConsultaKeyRef.current = null;
        setData({
            ...data,
            documento_tipo: value,
            documento_numero: numero,
        });
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };
        const opts = { preserveScroll: true, onSuccess };
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
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2"
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
                    title={t('usuarios:form.section_identity')}
                    description={t('usuarios:form.section_identity_hint')}
                    columns={2}
                    className="gap-4"
                >
                    <FormField
                        id="user-documento-tipo"
                        label={t('usuarios:form.fields.documento_tipo')}
                        error={errors.documento_tipo}
                    >
                        <DocumentTypeSelect
                            id="user-documento-tipo"
                            value={data.documento_tipo}
                            onValueChange={handleTipoChange}
                            codes={STAFF_DOCUMENT_TYPE_CODES}
                            invalid={Boolean(errors.documento_tipo)}
                        />
                    </FormField>

                    <FormField
                        id="user-documento-numero"
                        label={t('usuarios:form.fields.documento_numero')}
                        error={errors.documento_numero}
                    >
                        <DocumentNumberLookupField
                            id="user-documento-numero"
                            value={data.documento_numero}
                            onChange={(next) => setData('documento_numero', next)}
                            maxLength={docMaxLen}
                            consulting={consultandoDoc}
                            disabled={processing}
                            invalid={Boolean(errors.documento_numero)}
                            onConsult={() => void onConsultarDni()}
                            consultAriaLabel={t('propietarios:form.consultar_sunat')}
                        />
                    </FormField>

                    <FormField
                        id="user-name"
                        label={t('usuarios:form.fields.name')}
                        required
                        error={errors.name}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="user-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t('usuarios:form.fields.name_placeholder')}
                            autoComplete="off"
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
