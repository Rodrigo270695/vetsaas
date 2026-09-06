import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/forms';
import { Input } from '@/components/ui/input';
import {
    CAJA_BILLETERAS,
    type CajaBilleteraCodigo,
    type SaldosBilleterasForm,
} from './arqueo-types';

type BilleterasMontosFieldsProps = {
    idPrefix: string;
    values: SaldosBilleterasForm;
    onChange: (codigo: CajaBilleteraCodigo, value: string) => void;
    errors?: Record<string, string | undefined>;
    errorPrefix: 'saldos_apertura' | 'saldos_cierre';
};

function nestedError(
    errors: Record<string, string | undefined> | undefined,
    prefix: string,
    codigo: string,
): string | undefined {
    if (!errors) {
        return undefined;
    }

    return errors[`${prefix}.${codigo}`] ?? errors[`${prefix}[${codigo}]`];
}

export function BilleterasMontosFields({
    idPrefix,
    values,
    onChange,
    errors,
    errorPrefix,
}: BilleterasMontosFieldsProps) {
    const { t } = useTranslation('caja');

    return (
        <div className="flex flex-col gap-3">
            <div>
                <p className="text-sm font-medium">{t('sesiones.fields.saldos_billeteras')}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {errorPrefix === 'saldos_cierre'
                        ? t('sesiones.dialog_cerrar.billeteras_hint')
                        : t('sesiones.fields.saldos_billeteras_hint')}
                </p>
            </div>
            <ul className="divide-y divide-border/50 overflow-hidden rounded-xl border border-border/60">
                {CAJA_BILLETERAS.map((codigo) => (
                    <li key={codigo} className="px-3 py-2.5">
                        <FormField
                            id={`${idPrefix}-${codigo}`}
                            label={t(`sesiones.dialog_cerrar.metodos.${codigo}`)}
                            error={nestedError(errors, errorPrefix, codigo)}
                        >
                            <Input
                                id={`${idPrefix}-${codigo}`}
                                type="number"
                                inputMode="decimal"
                                min={0}
                                step="0.01"
                                value={values[codigo]}
                                onChange={(ev) => onChange(codigo, ev.target.value)}
                                className="tabular-nums"
                            />
                        </FormField>
                    </li>
                ))}
            </ul>
        </div>
    );
}
