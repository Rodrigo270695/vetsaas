import {
    Building2,
    Check,
    Copy,
    FileText,
    Mail,
    MapPin,
    Notebook,
    Phone,
    User,
    UserCircle,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import type { Propietario } from '../types';

type FactProps = {
    icon: typeof Mail;
    label: string;
    emptyLabel: string;
    value?: string | null;
    href?: string | null;
    copyValue?: string | null;
    className?: string;
    multiline?: boolean;
};

function Fact({
    icon: Icon,
    label,
    emptyLabel,
    value,
    href,
    copyValue,
    className,
    multiline = false,
}: FactProps) {
    const { t } = useTranslation('propietarios');
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);
    const filled = Boolean(value && value.trim() !== '');
    const text = value?.trim() ?? '';
    const toCopy = (copyValue ?? text).trim();

    const onCopy = async () => {
        if (toCopy === '') {
            return;
        }
        const ok = await copy(toCopy);
        if (!ok) {
            return;
        }
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1400);
    };

    const body =
        filled && href ? (
            <a
                href={href}
                className="wrap-break-word text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
            >
                {text}
            </a>
        ) : filled ? (
            <span className={cn('wrap-break-word text-foreground', multiline && 'whitespace-pre-wrap font-normal')}>
                {text}
            </span>
        ) : (
            <span className="text-muted-foreground/80">{emptyLabel}</span>
        );

    return (
        <div
            className={cn(
                'group relative flex gap-3 rounded-xl border bg-background/70 p-3 shadow-xs',
                'transition-[border-color,box-shadow,transform] duration-300 ease-out',
                'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300',
                filled
                    ? 'border-border/80 hover:border-primary/30 hover:shadow-sm'
                    : 'border-dashed border-border/70',
                className,
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1',
                    filled
                        ? 'bg-primary/10 text-primary ring-primary/15'
                        : 'bg-muted/60 text-muted-foreground ring-border/50',
                )}
            >
                <Icon className="size-4" strokeWidth={2} aria-hidden />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-[0.68rem] font-semibold uppercase tracking-wider text-muted-foreground">
                    {label}
                </p>
                <div className="mt-0.5 text-sm font-medium leading-snug">{body}</div>
            </div>
            {toCopy !== '' ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 cursor-pointer text-muted-foreground opacity-70 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                    onClick={() => void onCopy()}
                    aria-label={t('show.copy')}
                >
                    {copied ? (
                        <Check className="size-3.5 text-emerald-600" aria-hidden />
                    ) : (
                        <Copy className="size-3.5" aria-hidden />
                    )}
                </Button>
            ) : null}
        </div>
    );
}

function telHref(raw: string | null | undefined): string | null {
    if (!raw?.trim()) {
        return null;
    }
    const compact = raw.replace(/[^\d+]/g, '');

    return compact !== '' ? `tel:${compact}` : null;
}

type Props = {
    propietario: Propietario;
    docResumen: string | null;
};

export function PropietarioTitularCard({ propietario, docResumen }: Props) {
    const { t } = useTranslation('propietarios');
    const empty = t('show.empty_value');
    const ubicacion = [propietario.departamento, propietario.provincia, propietario.distrito]
        .filter(Boolean)
        .join(' · ');

    return (
        <section
            className="relative overflow-hidden rounded-2xl border border-border/60 bg-card/90 shadow-sm ring-1 ring-black/3 backdrop-blur-sm dark:bg-card/80 dark:ring-white/6"
            aria-labelledby="titular-heading"
        >
            <div
                className="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-linear-to-br from-primary/18 to-transparent blur-2xl"
                aria-hidden
            />
            <div className="relative space-y-4 p-4 sm:p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-primary/15 to-primary/5 text-primary shadow-inner ring-1 ring-primary/10">
                            <UserCircle className="size-7" strokeWidth={1.75} aria-hidden />
                        </span>
                        <div className="min-w-0">
                            <h2
                                id="titular-heading"
                                className="text-base font-semibold tracking-tight text-foreground"
                            >
                                {t('show.section_owner')}
                            </h2>
                            <p className="mt-0.5 max-w-lg text-sm leading-relaxed text-muted-foreground">
                                {t('show.section_owner_hint')}
                            </p>
                        </div>
                    </div>
                    {propietario.activo ? (
                        <StatBadge
                            label={t('show.owner_status_label')}
                            value={t('show.owner_active')}
                            variant="success"
                        />
                    ) : (
                        <StatBadge
                            label={t('show.owner_status_label')}
                            value={t('show.owner_inactive')}
                            variant="muted"
                        />
                    )}
                </div>

                {propietario.razon_social ? (
                    <div className="flex items-center gap-2 rounded-xl border border-primary/15 bg-primary/5 px-3 py-2 text-sm font-medium">
                        <Building2 className="size-4 shrink-0 text-primary" aria-hidden />
                        <span className="min-w-0 wrap-break-word">{propietario.razon_social}</span>
                    </div>
                ) : null}

                <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                    <Fact
                        icon={User}
                        label={t('form.nombres')}
                        emptyLabel={empty}
                        value={propietario.nombres}
                    />
                    <Fact
                        icon={User}
                        label={t('form.apellidos')}
                        emptyLabel={empty}
                        value={propietario.apellidos}
                    />
                    <Fact
                        icon={FileText}
                        label={t('show.label_doc')}
                        emptyLabel={t('row.no_doc')}
                        value={docResumen}
                        copyValue={propietario.numero_documento}
                    />
                    <Fact
                        icon={Mail}
                        label={t('show.label_email')}
                        emptyLabel={empty}
                        value={propietario.email}
                        href={
                            propietario.email?.trim()
                                ? `mailto:${propietario.email.trim()}`
                                : null
                        }
                    />
                    <Fact
                        icon={Phone}
                        label={t('show.label_phone')}
                        emptyLabel={empty}
                        value={propietario.telefono}
                        href={telHref(propietario.telefono)}
                    />
                    <Fact
                        icon={Phone}
                        label={t('form.telefono_alt')}
                        emptyLabel={empty}
                        value={propietario.telefono_alt}
                        href={telHref(propietario.telefono_alt)}
                    />
                    <Fact
                        icon={MapPin}
                        label={t('show.label_address')}
                        emptyLabel={empty}
                        value={propietario.direccion}
                        className="sm:col-span-2"
                    />
                    <Fact
                        icon={MapPin}
                        label={t('show.label_location')}
                        emptyLabel={empty}
                        value={ubicacion || null}
                    />
                    <Fact
                        icon={Notebook}
                        label={t('show.label_notes')}
                        emptyLabel={empty}
                        value={propietario.notas}
                        copyValue={propietario.notas}
                        multiline
                        className="sm:col-span-2 xl:col-span-3"
                    />
                </div>
            </div>
        </section>
    );
}
