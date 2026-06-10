import { useEffect } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { resolveDefaultSedeId, shouldShowSedeSelector } from '@/lib/default-sede';
import { FormField } from './form-field';

export type SedeFormOption = {
    id: string;
    nombre: string;
    codigo?: string | null;
};

export type SedeFormFieldProps = {
    id: string;
    label: string;
    sedes: readonly SedeFormOption[];
    value: string | null;
    onChange: (sedeId: string | null) => void;
    error?: string;
    disabled?: boolean;
    allowNone?: boolean;
    noneLabel?: string;
    required?: boolean;
    hint?: string;
    controlClassName?: string;
    formatLabel?: (sede: SedeFormOption) => string;
};

function defaultFormatLabel(sede: SedeFormOption): string {
    return sede.codigo ? `${sede.nombre} (${sede.codigo})` : sede.nombre;
}

/**
 * Selector de sede para modales. Si solo hay una sede activa, no se muestra
 * y se asigna automáticamente al formulario.
 */
export function SedeFormField({
    id,
    label,
    sedes,
    value,
    onChange,
    error,
    disabled = false,
    allowNone = true,
    noneLabel,
    required,
    hint,
    controlClassName = 'h-10 w-full min-w-0',
    formatLabel = defaultFormatLabel,
}: SedeFormFieldProps) {
    const lockedSedeId = resolveDefaultSedeId(sedes);
    const showSelector = shouldShowSedeSelector(sedes);

    useEffect(() => {
        if (sedes.length === 1 && lockedSedeId && value !== lockedSedeId) {
            onChange(lockedSedeId);
        }
    }, [sedes, lockedSedeId, value, onChange]);

    if (!showSelector) {
        return null;
    }

    const selectValue = allowNone
        ? (value ?? '__none__')
        : (value && value !== '' ? value : undefined);

    return (
        <FormField id={id} label={label} required={required} hint={hint} error={error}>
            <Select
                value={selectValue}
                onValueChange={(v) => onChange(allowNone && v === '__none__' ? null : v)}
                disabled={disabled || sedes.length === 0}
            >
                <SelectTrigger id={id} className={controlClassName}>
                    <SelectValue placeholder={noneLabel} />
                </SelectTrigger>
                <SelectContent>
                    {allowNone && noneLabel ? (
                        <SelectItem value="__none__">{noneLabel}</SelectItem>
                    ) : null}
                    {sedes.map((sede) => (
                        <SelectItem key={sede.id} value={sede.id}>
                            {formatLabel(sede)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );
}
