import { useForm } from '@inertiajs/react';
import { Loader2, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
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
import usuarios from '@/routes/configuracion/usuarios';
import type { User, UserRoleOption } from '../types';

export type UserFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Usuario a editar; si es `null`, el modal abre en modo crear. */
    user: User | null;
    /** Catálogo de roles disponibles para el select. */
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
};

const emptyForm: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    role: '',
};

const buildInitialData = (user: User | null): UserFormData => ({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    password: '',
    password_confirmation: '',
    is_active: user?.is_active ?? true,
    role: user?.roles[0]?.name ?? '',
});

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const isFormValid = (data: UserFormData, isEdit: boolean): boolean => {
    if (data.name.trim().length < 2) return false;
    if (!EMAIL_REGEX.test(data.email.trim())) return false;
    if (!data.role) return false;
    // En create exigimos password. En edit es opcional, pero si el
    // usuario tecleó algo en password debe coincidir con la confirmación.
    if (!isEdit) {
        if (data.password.length < 8) return false;
        if (data.password !== data.password_confirmation) return false;
    } else if (data.password.length > 0) {
        if (data.password.length < 8) return false;
        if (data.password !== data.password_confirmation) return false;
    }
    return true;
};

/**
 * Modal de crear/editar usuario.
 *
 * Espejo de `RoleFormModal` y `SedeFormModal`:
 *   - Misma `FormSection` con título/hint + grilla 2 columnas.
 *   - En create, password obligatorio. En edit, dejar vacío conserva la
 *     contraseña actual (el backend ignora el campo si está vacío).
 *   - Select de rol único (Spatie soporta múltiples, pero por UX
 *     mantenemos 1 rol por usuario como define el dominio).
 *   - Checkbox `is_active` para suspender sin eliminar.
 */
export function UserFormModal({
    open,
    onOpenChange,
    user,
    rolesCatalog,
}: UserFormModalProps) {
    const { t } = useTranslation(['usuarios', 'common']);
    const isEdit = user !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<UserFormData>(emptyForm);

    const canSubmit = isFormValid(data, isEdit) && !processing;

    const initialSnapshotRef = useRef<UserFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(user);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof UserFormData>).forEach(
                (key) => {
                    setData(key, initial[key] as never);
                },
            );
            clearErrors();
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
            data.password.length > 0 ||
            data.password_confirmation.length > 0
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

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && user) {
            put(usuarios.update(user.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(usuarios.store().url, {
                preserveScroll: true,
                onSuccess,
            });
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
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
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
                            id="user-is-active"
                            label={t('usuarios:form.fields.is_active')}
                            hint={t('usuarios:form.fields.is_active_hint')}
                            error={errors.is_active}
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
                                        placeholder={t(
                                            'usuarios:form.fields.role_placeholder',
                                        )}
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

                        <div className="hidden sm:block" />

                        <FormField
                            id="user-password"
                            label={t('usuarios:form.fields.password')}
                            required={!isEdit}
                            error={errors.password}
                        >
                            <Input
                                id="user-password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder={
                                    isEdit
                                        ? t(
                                              'usuarios:form.fields.password_placeholder_edit',
                                          )
                                        : t(
                                              'usuarios:form.fields.password_placeholder',
                                          )
                                }
                                autoComplete="new-password"
                            />
                        </FormField>

                        <FormField
                            id="user-password-confirmation"
                            label={t('usuarios:form.fields.password_confirmation')}
                            required={!isEdit && data.password.length > 0}
                        >
                            <Input
                                id="user-password-confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                placeholder={
                                    isEdit
                                        ? t(
                                              'usuarios:form.fields.password_placeholder_edit',
                                          )
                                        : t(
                                              'usuarios:form.fields.password_placeholder',
                                          )
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
