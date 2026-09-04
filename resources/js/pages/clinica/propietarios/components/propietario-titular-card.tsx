import { Building2, Check, Copy, FileText, Mail, MapPin, Notebook, Phone } from 'lucide-react';
import { useState } from 'react';
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
            className="size-8 shrink-0 cursor-pointer text-muted-foreground"
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

type RowProps = {
    icon: typeof Phone;
    label: string;
    value: string;
    href?: string | null;
    copyText?: string;
    multiline?: boolean;
};

function ContactRow({ icon: Icon, label, value, href, copyText, multiline }: RowProps) {
    return (
        <div className="flex items-start gap-3 px-4 py-3.5 sm:px-5">
            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground/70">
                <Icon className="size-4" strokeWidth={2} aria-hidden />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium text-muted-foreground">{label}</p>
                {href ? (
                    <a
                        href={href}
                        className="mt-0.5 block text-sm font-medium text-foreground underline-offset-4 hover:text-primary hover:underline"
                    >
                        {value}
                    </a>
                ) : (
                    <p
                        className={cn(
                            'mt-0.5 text-sm font-medium text-foreground',
                            multiline && 'whitespace-pre-wrap font-normal leading-relaxed',
                        )}
                    >
                        {value}
                    </p>
                )}
            </div>
            {copyText ? <CopyButton text={copyText} /> : null}
        </div>
    );
}

type Props = {
    propietario: Propietario;
    displayName: string;
    docResumen: string | null;
};

export function PropietarioTitularCard({ propietario, displayName, docResumen }: Props) {
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

    const rows: RowProps[] = [];
    if (telefono) {
        rows.push({
            icon: Phone,
            label: t('show.label_phone'),
            value: telefono,
            href: telHref(telefono),
            copyText: telefono,
        });
    }
    if (telefonoAlt) {
        rows.push({
            icon: Phone,
            label: t('form.telefono_alt'),
            value: telefonoAlt,
            href: telHref(telefonoAlt),
            copyText: telefonoAlt,
        });
    }
    if (email) {
        rows.push({
            icon: Mail,
            label: t('show.label_email'),
            value: email,
            href: `mailto:${email}`,
            copyText: email,
        });
    }
    if (lugar) {
        rows.push({
            icon: MapPin,
            label: t('show.label_address'),
            value: lugar,
            copyText: lugar,
        });
    }
    if (notas) {
        rows.push({
            icon: Notebook,
            label: t('show.label_notes'),
            value: notas,
            copyText: notas,
            multiline: true,
        });
    }

    return (
        <section
            className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
            aria-labelledby="titular-heading"
        >
            <div className="flex flex-col gap-4 border-b border-border bg-muted/30 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                <div
                    className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary text-lg font-semibold tracking-wide text-primary-foreground"
                    aria-hidden
                >
                    {initials(displayName)}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2
                            id="titular-heading"
                            className="text-lg font-semibold tracking-tight text-foreground"
                        >
                            {displayName}
                        </h2>
                        {propietario.activo ? (
                            <Badge className="border-transparent bg-emerald-600/90 text-white hover:bg-emerald-600/90">
                                {t('show.owner_active')}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">{t('show.owner_inactive')}</Badge>
                        )}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        {propietario.razon_social &&
                        propietario.razon_social.trim() !== displayName.trim() ? (
                            <span className="inline-flex items-center gap-1.5">
                                <Building2 className="size-3.5 shrink-0" aria-hidden />
                                {propietario.razon_social}
                            </span>
                        ) : null}
                        {docResumen ? (
                            <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                <FileText className="size-3.5 shrink-0 text-muted-foreground" aria-hidden />
                                {docResumen}
                            </span>
                        ) : (
                            <span>{t('row.no_doc')}</span>
                        )}
                    </div>
                </div>
            </div>

            {rows.length > 0 ? (
                <div className="divide-y divide-border">
                    {rows.map((row) => (
                        <ContactRow key={`${row.label}-${row.value}`} {...row} />
                    ))}
                </div>
            ) : (
                <p className="px-4 py-4 text-sm text-muted-foreground sm:px-5">
                    {t('show.no_contact')}
                </p>
            )}
        </section>
    );
}
