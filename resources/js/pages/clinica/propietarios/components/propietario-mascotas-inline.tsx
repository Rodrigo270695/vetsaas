import { PawPrint, Plus, Sparkles, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    PACIENTE_ESPECIES,
    PACIENTE_RAZAS,
    toComboboxOptions,
} from '@/lib/paciente-especie-raza-options';
import { cn } from '@/lib/utils';

export type MascotaDraft = {
    key: string;
    nombre: string;
    especie: string;
    raza: string;
    sexo: '' | 'M' | 'H' | 'U';
    fecha_nacimiento: string;
    peso_kg: string;
    color: string;
    esterilizado: '' | 'yes' | 'no';
};

export function emptyMascotaDraft(): MascotaDraft {
    return {
        key: crypto.randomUUID(),
        nombre: '',
        especie: '',
        raza: '',
        sexo: '',
        fecha_nacimiento: '',
        peso_kg: '',
        color: '',
        esterilizado: '',
    };
}

export function mascotaDraftsForSubmit(drafts: readonly MascotaDraft[]): Array<
    Omit<MascotaDraft, 'key'>
> {
    return drafts
        .filter((row) => row.nombre.trim() !== '')
        .map(({ key: _key, ...rest }) => rest);
}

type Props = {
    drafts: MascotaDraft[];
    onChange: (next: MascotaDraft[]) => void;
    errors: Record<string, string>;
    disabled?: boolean;
};

export function PropietarioMascotasInline({
    drafts,
    onChange,
    errors,
    disabled = false,
}: Props) {
    const { t } = useTranslation(['propietarios', 'pacientes']);
    const especieOptions = useMemo(() => toComboboxOptions([...PACIENTE_ESPECIES]), []);
    const razaOptions = useMemo(() => toComboboxOptions([...PACIENTE_RAZAS]), []);
    const namedCount = drafts.filter((row) => row.nombre.trim() !== '').length;

    const patch = (index: number, partial: Partial<MascotaDraft>) => {
        onChange(drafts.map((row, i) => (i === index ? { ...row, ...partial } : row)));
    };

    const remove = (index: number) => {
        if (drafts.length <= 1) {
            onChange([emptyMascotaDraft()]);

            return;
        }
        onChange(drafts.filter((_, i) => i !== index));
    };

    return (
        <section
            className="relative overflow-hidden rounded-2xl border border-primary/15 bg-linear-to-b from-primary/6 via-background to-background p-4 shadow-sm sm:p-5"
            aria-labelledby="prop-mascotas-heading"
        >
            <div
                className="pointer-events-none absolute -right-10 -top-12 size-36 rounded-full bg-primary/10 blur-2xl"
                aria-hidden
            />
            <div className="relative flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 flex size-10 items-center justify-center rounded-xl bg-primary/15 text-primary shadow-inner">
                        <PawPrint className="size-5" aria-hidden />
                    </span>
                    <div>
                        <h3
                            id="prop-mascotas-heading"
                            className="text-sm font-semibold tracking-tight"
                        >
                            {t('form.section_pets')}
                        </h3>
                        <p className="mt-0.5 max-w-md text-xs leading-relaxed text-muted-foreground">
                            {t('form.section_pets_hint')}
                        </p>
                    </div>
                </div>
                {namedCount > 0 ? (
                    <p className="text-xs font-medium text-primary tabular-nums">
                        {t('form.pets_named_count', { count: namedCount })}
                    </p>
                ) : null}
            </div>

            <div className="relative mt-4 flex flex-col gap-3">
                {drafts.map((draft, index) => (
                    <article
                        key={draft.key}
                        className={cn(
                            'group rounded-xl border border-border/80 bg-card/90 p-3 shadow-sm backdrop-blur-sm',
                            'transition-[border-color,box-shadow,transform] duration-300 ease-out',
                            'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-2 motion-safe:duration-300',
                            'hover:border-primary/30 focus-within:border-primary/40 focus-within:shadow-md',
                            draft.nombre.trim() !== '' && 'border-primary/25 ring-1 ring-primary/10',
                        )}
                        style={{ animationDelay: `${Math.min(index, 6) * 40}ms` }}
                    >
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <p className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                <span className="flex size-6 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground">
                                    {index + 1}
                                </span>
                                {draft.nombre.trim() !== ''
                                    ? draft.nombre.trim()
                                    : t('form.pet_untitled')}
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 cursor-pointer px-2 text-muted-foreground hover:text-destructive"
                                disabled={disabled}
                                onClick={() => remove(index)}
                                aria-label={t('form.pet_remove')}
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <FormField
                                id={`pet-${draft.key}-nombre`}
                                label={t('pacientes:form.nombre')}
                                error={errors[`mascotas.${index}.nombre`]}
                            >
                                <Input
                                    id={`pet-${draft.key}-nombre`}
                                    value={draft.nombre}
                                    disabled={disabled}
                                    placeholder={t('form.pet_name_placeholder')}
                                    onChange={(e) => patch(index, { nombre: e.target.value })}
                                />
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-especie`}
                                label={t('pacientes:form.especie')}
                                error={errors[`mascotas.${index}.especie`]}
                            >
                                <Combobox
                                    options={especieOptions}
                                    value={draft.especie || null}
                                    onChange={(value) => patch(index, { especie: value ?? '' })}
                                    placeholder={t('pacientes:form.especie_placeholder')}
                                    searchPlaceholder={t('pacientes:form.especie_search')}
                                    emptyMessage={t('pacientes:form.especie_empty')}
                                    createOptionLabel={(value) =>
                                        t('pacientes:form.especie_create', { value })
                                    }
                                    creatable
                                    disabled={disabled}
                                    className="h-10 w-full cursor-pointer"
                                />
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-raza`}
                                label={t('pacientes:form.raza')}
                                error={errors[`mascotas.${index}.raza`]}
                            >
                                <Combobox
                                    options={razaOptions}
                                    value={draft.raza || null}
                                    onChange={(value) => patch(index, { raza: value ?? '' })}
                                    placeholder={t('pacientes:form.raza_placeholder')}
                                    searchPlaceholder={t('pacientes:form.raza_search')}
                                    emptyMessage={t('pacientes:form.raza_empty')}
                                    createOptionLabel={(value) =>
                                        t('pacientes:form.raza_create', { value })
                                    }
                                    creatable
                                    disabled={disabled}
                                    className="h-10 w-full cursor-pointer"
                                />
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-sexo`}
                                label={t('pacientes:form.sexo')}
                                error={errors[`mascotas.${index}.sexo`]}
                            >
                                <Select
                                    value={draft.sexo === '' ? '__empty' : draft.sexo}
                                    onValueChange={(value) =>
                                        patch(index, {
                                            sexo: value === '__empty' ? '' : (value as MascotaDraft['sexo']),
                                        })
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id={`pet-${draft.key}-sexo`} className="w-full">
                                        <SelectValue placeholder={t('pacientes:form.sexo_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__empty">
                                            {t('pacientes:form.sexo_placeholder')}
                                        </SelectItem>
                                        <SelectItem value="M">{t('pacientes:form.sexo_m')}</SelectItem>
                                        <SelectItem value="H">{t('pacientes:form.sexo_h')}</SelectItem>
                                        <SelectItem value="U">{t('pacientes:form.sexo_u')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-fn`}
                                label={t('pacientes:form.fecha_nacimiento')}
                                error={errors[`mascotas.${index}.fecha_nacimiento`]}
                            >
                                <Input
                                    id={`pet-${draft.key}-fn`}
                                    type="date"
                                    value={draft.fecha_nacimiento}
                                    disabled={disabled}
                                    onChange={(e) =>
                                        patch(index, { fecha_nacimiento: e.target.value })
                                    }
                                />
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-peso`}
                                label={t('pacientes:form.peso_kg')}
                                error={errors[`mascotas.${index}.peso_kg`]}
                            >
                                <Input
                                    id={`pet-${draft.key}-peso`}
                                    inputMode="decimal"
                                    value={draft.peso_kg}
                                    disabled={disabled}
                                    onChange={(e) => patch(index, { peso_kg: e.target.value })}
                                />
                            </FormField>
                            <FormField
                                id={`pet-${draft.key}-color`}
                                label={t('pacientes:form.color')}
                                error={errors[`mascotas.${index}.color`]}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id={`pet-${draft.key}-color`}
                                    value={draft.color}
                                    disabled={disabled}
                                    onChange={(e) => patch(index, { color: e.target.value })}
                                />
                            </FormField>
                        </div>
                    </article>
                ))}
            </div>

            <Button
                type="button"
                variant="outline"
                className="relative mt-3 h-10 w-full cursor-pointer gap-2 border-dashed border-primary/30 bg-background/60 text-primary transition-all duration-200 hover:border-primary/50 hover:bg-primary/5 hover:shadow-sm active:scale-[0.99]"
                disabled={disabled || drafts.length >= 15}
                onClick={() => onChange([...drafts, emptyMascotaDraft()])}
            >
                <Plus className="size-4" />
                {t('form.pet_add')}
                <Sparkles className="size-3.5 opacity-60" aria-hidden />
            </Button>
        </section>
    );
}
