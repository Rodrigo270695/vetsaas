import { useTranslation } from 'react-i18next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { PROPIETARIO_DOCUMENT_TYPE_CODES } from '@/lib/document-type-options';

const NONE_VALUE = '__none__';

/** Códigos de documento para staff/usuarios (sin RUC). */
export const STAFF_DOCUMENT_TYPE_CODES = ['DNI', 'CE', 'PAS', 'OTRO'] as const;

export type StaffDocumentTypeCode = (typeof STAFF_DOCUMENT_TYPE_CODES)[number];

export function isStaffDocumentTypeCode(value: string): value is StaffDocumentTypeCode {
    return (STAFF_DOCUMENT_TYPE_CODES as readonly string[]).includes(value);
}

export type DocumentTypeSelectProps = {
    id: string;
    /** Código guardado en backend (`DNI`, `RUC`, …) o cadena vacía si no aplica. */
    value: string;
    onValueChange: (next: string) => void;
    disabled?: boolean;
    className?: string;
    /** Activa borde de error (el mensaje lo muestra `FormField`). */
    invalid?: boolean;
    /**
     * Catálogo de códigos. Default: titulares (incluye RUC).
     * Para usuarios/staff pasa `STAFF_DOCUMENT_TYPE_CODES`.
     */
    codes?: readonly string[];
};

/**
 * Selector de tipo de documento.
 * Labels desde namespace `propietarios` (`form.document_type_*`).
 * `OTRO` reutiliza la etiqueta de `OTR`.
 */
export function DocumentTypeSelect({
    id,
    value,
    onValueChange,
    disabled,
    className,
    invalid,
    codes = PROPIETARIO_DOCUMENT_TYPE_CODES,
}: DocumentTypeSelectProps) {
    const { t } = useTranslation('propietarios');
    const known = codes.includes(value) ? value : NONE_VALUE;

    const labelFor = (code: string): string => {
        if (code === 'OTRO') {
            return t('form.document_type_otr');
        }
        return t(`form.document_type_${code.toLowerCase()}`);
    };

    return (
        <Select
            value={known}
            onValueChange={(v) => {
                onValueChange(v === NONE_VALUE ? '' : v);
            }}
            disabled={disabled}
        >
            <SelectTrigger
                id={id}
                aria-invalid={invalid}
                className={cn(
                    'w-full cursor-pointer',
                    invalid && 'border-destructive',
                    className,
                )}
            >
                <SelectValue placeholder={t('form.document_type_placeholder')} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE_VALUE} className="cursor-pointer">
                    {t('form.document_type_placeholder')}
                </SelectItem>
                {codes.map((code) => (
                    <SelectItem key={code} value={code} className="cursor-pointer">
                        {labelFor(code)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
