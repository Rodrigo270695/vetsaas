import { useTranslation } from 'react-i18next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import {
    PROPIETARIO_DOCUMENT_TYPE_CODES,
    isPropietarioDocumentTypeCode,
} from '@/lib/document-type-options';

const NONE_VALUE = '__none__';

export type DocumentTypeSelectProps = {
    id: string;
    /** Código guardado en backend (`DNI`, `RUC`, …) o cadena vacía si no aplica. */
    value: string;
    onValueChange: (next: string) => void;
    disabled?: boolean;
    className?: string;
    /** Activa borde de error (el mensaje lo muestra `FormField`). */
    invalid?: boolean;
};

function i18nKeyForCode(code: (typeof PROPIETARIO_DOCUMENT_TYPE_CODES)[number]): string {
    return `form.document_type_${code.toLowerCase()}`;
}

/**
 * Selector de tipo de documento (DNI, RUC, CE, pasaporte, otro).
 * Textos bajo namespace `propietarios` (`form.document_type_*`, `form.document_type_placeholder`).
 */
export function DocumentTypeSelect({
    id,
    value,
    onValueChange,
    disabled,
    className,
    invalid,
}: DocumentTypeSelectProps) {
    const { t } = useTranslation('propietarios');
    const selectValue =
        value && isPropietarioDocumentTypeCode(value) ? value : NONE_VALUE;

    return (
        <Select
            value={selectValue}
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
                {PROPIETARIO_DOCUMENT_TYPE_CODES.map((code) => (
                    <SelectItem key={code} value={code} className="cursor-pointer">
                        {t(i18nKeyForCode(code))}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
