import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import roles from '@/routes/configuracion/roles';
import type { Role } from '../types';

export type RoleFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Rol a editar; si es `null`, el modal abre en modo crear. */
    role: Role | null;
};

type RoleFormData = {
    name: string;
    description: string;
};

const emptyForm: RoleFormData = {
    name: '',
    description: '',
};

const buildInitialData = (role: Role | null): RoleFormData => ({
    name: role?.name ?? '',
    description: role?.description ?? '',
});

/**
 * Para habilitar el botón "Guardar" exigimos un nombre válido:
 *   - Mínimo 2 caracteres.
 *   - Solo lowercase, dígitos y guiones bajos (convención Spatie + UX).
 */
const NAME_PATTERN = /^[a-z0-9_]{2,}$/;
const isFormValid = (data: RoleFormData): boolean =>
    NAME_PATTERN.test(data.name.trim());

/**
 * Modal compacto de crear/editar rol.
 *
 * Diseño deliberadamente minimalista: SOLO maneja la metadata del rol
 * (nombre + descripción). La gestión de permisos vive en un modal
 * separado (`RolePermissionsModal`) que se abre desde el row-action
 * "Gestionar permisos". Esta separación:
 *   - Reduce el tamaño cognitivo del modal de creación.
 *   - Permite asignar/quitar permisos sin tocar la metadata.
 *   - Refleja la realidad: en operación normal cambias permisos mucho
 *     más seguido que el nombre/descripción del rol.
 */
export function RoleFormModal({
    open,
    onOpenChange,
    role,
}: RoleFormModalProps) {
    const { t } = useTranslation(['roles', 'common']);
    const isEdit = role !== null;
    const isSystem = role?.is_system === true;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<RoleFormData>(emptyForm);

    const canSubmit = isFormValid(data) && !processing && !isSystem;

    const initialSnapshotRef = useRef<RoleFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(role);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof RoleFormData>).forEach(
                (key) => {
                    setData(key, initial[key]);
                },
            );
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.id]);

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return initial.name !== data.name ||
            initial.description !== data.description;
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

        if (isEdit && role) {
            put(roles.update(role.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(roles.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('roles:form.title_edit') : t('roles:form.title_create')}
            description={
                isEdit
                    ? t('roles:form.description_edit')
                    : t('roles:form.description_create')
            }
            size="md"
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
                            ? t('roles:form.submit_edit')
                            : t('roles:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('roles:form.section_basic')}
                    description={
                        isEdit
                            ? t('roles:form.section_basic_hint_edit')
                            : t('roles:form.section_basic_hint_create')
                    }
                >
                    <FormField
                        id="role-name"
                        label={t('roles:form.fields.name')}
                        required
                        error={errors.name}
                        hint={t('roles:form.fields.name_hint')}
                    >
                        <Input
                            id="role-name"
                            value={data.name}
                            onChange={(e) =>
                                setData(
                                    'name',
                                    e.target.value
                                        .toLowerCase()
                                        .replace(/\s+/g, '_'),
                                )
                            }
                            placeholder={t('roles:form.fields.name_placeholder')}
                            autoComplete="off"
                            autoFocus
                            disabled={isSystem}
                            className="font-mono"
                        />
                    </FormField>

                    <FormField
                        id="role-description"
                        label={t('roles:form.fields.description')}
                        error={errors.description}
                    >
                        <Textarea
                            id="role-description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            placeholder={t(
                                'roles:form.fields.description_placeholder',
                            )}
                            disabled={isSystem}
                            rows={3}
                        />
                    </FormField>

                    {isEdit && (
                        <p className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                            {t('roles:form.permissions_hint_edit')}
                        </p>
                    )}
                </FormSection>
            </div>
        </FormModal>
    );
}
