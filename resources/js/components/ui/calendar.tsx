import { es } from 'date-fns/locale';
import { type ComponentProps } from 'react';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/style.css';

import { cn } from '@/lib/utils';
import { VetsaasDayPickerDropdown } from '@/components/ui/day-picker-dropdown';

import './calendar-rdp.css';

export type CalendarProps = ComponentProps<typeof DayPicker>;

const defaultCaptionLayout = 'dropdown' as const;

/**
 * Calendario (react-day-picker) con estilos VetSaaS y caption con mes/año en desplegables.
 */
export function Calendar({
    className,
    classNames,
    captionLayout = defaultCaptionLayout,
    locale = es,
    components: userComponents,
    ...props
}: CalendarProps) {
    const y = new Date().getFullYear();

    return (
        <DayPicker
            captionLayout={captionLayout}
            locale={locale}
            startMonth={new Date(1990, 0)}
            endMonth={new Date(y + 5, 11)}
            navLayout="around"
            className={cn('vetsaas-rdp rdp-root p-2', className)}
            classNames={classNames}
            components={{
                ...userComponents,
                Dropdown: VetsaasDayPickerDropdown,
            }}
            {...props}
        />
    );
}
