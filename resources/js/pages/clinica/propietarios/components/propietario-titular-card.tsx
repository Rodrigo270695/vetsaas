import { Building2, Check, Copy, FileText, Mail, MapPin, Notebook, Phone } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { Propietario } from '../types';

function telHref(raw: string | null | undefined): string | null {
    if (!raw?.trim()) {
        return null;
    }
    const compact = raw.replace(/[^\d+]/g, '');

    return compact !== '' ? `tel:${compact}` : null;
}

function CopyButton({ text }: { text: string }) {
    const { t } = useTranslation('propietarios');
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 shrink-0 cursor-pointer text-muted-foreground"
            onClick={() => {
                void copy(text).then((ok) => {
                    if (!ok) {
                        return;
                    }
                    setCopied(true);
                    window.setTimeout(() => setCopied(false), 1400);
                });
            }}
            aria-label={t('show.copy')}
        >
            {copied ? (
                <Check className="size-3.5 text-emerald-600" aria-hidden />
            ) : (
                <Copy className="size-3.5" aria-hidden />
            )}
        </Button>
    );
}

type ChipProps = {
    icon: typeof Phone;
    label: string;
    value: string;
    href?: string | null;
};

function ContactChip({ icon: Icon, label, value, href }: ChipProps) {
    return (
        <div className="flex min-w-0 items-start gap-2.5">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" strokeWidth={2} aria-hidden />
            <div className="min-w-0">
                <p className="text-[0.7rem] font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </p>
                {href ? (
                    <a
                        href={href}
                        className="block truncate text-sm font-medium text-foreground hover:text-primary hover:underline"
                    >
                        {value}
                    </a>
                ) : (
                    <p className="wrap-break-word text-sm font-medium text-foreground">{value}</p>
                )}
            </div>
        </div>
    );
}

type Props = {
    propietario: Propietario;
    displayName: string;
    docResumen: string | null;
    actions: ReactNode;
};

export function PropietarioTitularCard({
    propietario,
    displayName,
    docResumen,
    actions,
}: Props) {
    const { t } = useTranslation('propietarios');
    const initials = useInitials();
    const email = propietario.email?.trim() || '';
    const telefono = propietario.telefono?.trim() || '';
    const telefonoAlt = propietario.telefono_alt?.trim() || '';
    const direccion = propietario.direccion?.trim() || '';
    const ubicacion = [propietario.departamento, propietario.provincia, propietario.distrito]
        .filter(Boolean)
        .join(' · ');
    const notas = propietario.notas?.trim() || '';
    const lugar = [direccion, ubicacion].filter(Boolean).join(' · ');

    const chips: ChipProps[] = [];
    if (telefono) {
        chips.push({
            icon: Phone,
            label: t('show.label_phone'),
            value: telefono,
            href: telHref(telefono),
        });
    }
    if (telefonoAlt) {
        chips.push({
            icon: Phone,
            label: t('form.telefono_alt'),
            value: telefonoAlt,
            href: telHref(telefonoAlt),
        });
    }
    if (email) {
        chips.push({
            icon: Mail,
            label: t('show.label_email'),
            value: email,
            href: `mailto:${email}`,
        });
    }
    if (lugar) {
        chips.push({
            icon: MapPin,
            label: t('show.label_address'),
            value: lugar,
        });
    }

    return (
        <header className="rounded-2xl border border-border bg-card shadow-sm">
            <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                <div
                    className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary text-lg font-semibold tracking-wide text-primary-foreground sm:size-16 sm:text-xl"
                    aria-hidden
                >
                    {initials(displayName)}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                            {displayName}
                        </h1>
                        <Badge
                            variant="outline"
                            className="border-primary/25 bg-primary/5 font-normal text-primary"
                        >
                            {t('show.badge_titular')}
                        </Badge>
                        {propietario.activo ? (
                            <Badge className="border-transparent bg-emerald-600/90 text-white hover:bg-emerald-600/90">
                                {t('show.owner_active')}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">{t('show.owner_inactive')}</Badge>
                        )}
                    </div>
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        {propietario.razon_social &&
                        propietario.razon_social.trim() !== displayName.trim() ? (
                            <span className="inline-flex items-center gap-1.5">
                                <Building2 className="size-3.5 shrink-0" aria-hidden />
                                {propietario.razon_social}
                            </span>
                        ) : null}
                        {docResumen ? (
                            <span className="inline-flex items-center gap-1">
                                <FileText className="size-3.5 shrink-0" aria-hidden />
                                <span className="font-medium text-foreground">{docResumen}</span>
                                <CopyButton text={propietario.numero_documento?.trim() || docResumen} />
                            </span>
                        ) : (
                            <span>{t('row.no_doc')}</span>
                        )}
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap gap-2 sm:justify-end">{actions}</div>
            </div>

            {chips.length > 0 || notas ? (
                <div className="border-t border-border px-4 py-4 sm:px-5">
                    {chips.length > 0 ? (
                        <div
                            className={cn(
                                'grid gap-4',
                                chips.length === 1 && 'sm:grid-cols-1',
                                chips.length === 2 && 'sm:grid-cols-2',
                                chips.length >= 3 && 'sm:grid-cols-2 lg:grid-cols-3',
                            )}
                        >
                            {chips.map((chip) => (
                                <ContactChip key={`${chip.label}-${chip.value}`} {...chip} />
                            ))}
                        </div>
                    ) : null}
                    {notas ? (
                        <div className={cn('flex items-start gap-2.5', chips.length > 0 && 'mt-4')}>
                            <Notebook
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                strokeWidth={2}
                                aria-hidden
                            />
                            <div className="min-w-0">
                                <p className="text-[0.7rem] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('show.label_notes')}
                                </p>
                                <p className="mt-0.5 whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                                    {notas}
                                </p>
                            </div>
                        </div>
                    ) : null}
                </div>
            ) : null}
        </header>
    );
}
