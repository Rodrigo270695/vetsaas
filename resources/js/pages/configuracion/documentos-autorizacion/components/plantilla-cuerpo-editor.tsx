import {
    AlignCenter,
    AlignLeft,
    Bold,
    ImageIcon,
    Italic,
    List,
    ListOrdered,
    Underline,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const VAR_GROUPS: readonly { label: string; items: readonly string[] }[] = [
    { label: 'Paciente', items: ['paciente', 'especie', 'raza', 'edad', 'sexo'] },
    { label: 'Titular', items: ['propietario', 'documento', 'telefono'] },
    { label: 'Clínica', items: ['clinica', 'ciudad', 'veterinario', 'logo'] },
    { label: 'Consulta', items: ['motivo', 'causa'] },
    { label: 'Fecha', items: ['fecha', 'fecha_corta', 'dia', 'mes', 'mes_nombre', 'anio'] },
];

export function htmlToPlain(html: string): string {
    if (typeof document === 'undefined') {
        return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }
    const el = document.createElement('div');
    el.innerHTML = html;

    return (el.textContent ?? '').replace(/\s+/g, ' ').trim();
}

export function cuerpoTieneTexto(html: string): boolean {
    return htmlToPlain(html.replace(/&nbsp;/gi, ' ')).length > 0;
}

type Props = {
    value: string;
    onChange: (html: string) => void;
    resetKey: string;
    logoUrl?: string | null;
    disabled?: boolean;
};

function run(cmd: string, arg?: string) {
    document.execCommand(cmd, false, arg);
}

export function PlantillaCuerpoEditor({ value, onChange, resetKey, logoUrl, disabled }: Props) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        el.innerHTML = value.trim() !== '' ? value : '<p><br></p>';
        el.querySelectorAll('img.auth-doc-logo').forEach((img) => {
            if (logoUrl) {
                img.setAttribute('src', logoUrl);
            } else {
                img.removeAttribute('src');
            }
        });
        // Solo al abrir / cambiar de plantilla. Si se sincroniza en cada tecla, el cursor salta.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resetKey, logoUrl]);

    const insertVar = (key: string) => {
        if (key === 'logo') {
            insertLogo();
            return;
        }
        const el = ref.current;
        if (!el || disabled) {
            return;
        }
        el.focus();
        const token = `{{${key}}}`;
        const ok = document.execCommand('insertText', false, token);
        if (!ok) {
            document.execCommand('insertHTML', false, token);
        }
        onChange(el.innerHTML);
    };

    const insertLogo = () => {
        const el = ref.current;
        if (!el || disabled) {
            return;
        }
        el.focus();
        const src = logoUrl
            ? logoUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            : '';
        const html = `<p style="text-align:center"><img class="auth-doc-logo" alt=""${src ? ` src="${src}"` : ''}></p>`;
        document.execCommand('insertHTML', false, html);
        onChange(el.innerHTML);
    };

    const format = (cmd: string) => {
        const el = ref.current;
        if (!el || disabled) {
            return;
        }
        el.focus();
        run(cmd);
        onChange(el.innerHTML);
    };

    return (
        <div className="overflow-hidden rounded-lg border border-primary/20 bg-background shadow-sm">
            <div className="flex flex-wrap gap-1 border-b border-primary/15 bg-primary/8 p-1.5">
                <ToolbarBtn icon={Bold} label="Negrita" onClick={() => format('bold')} disabled={disabled} />
                <ToolbarBtn icon={Italic} label="Cursiva" onClick={() => format('italic')} disabled={disabled} />
                <ToolbarBtn
                    icon={Underline}
                    label="Subrayado"
                    onClick={() => format('underline')}
                    disabled={disabled}
                />
                <ToolbarBtn
                    icon={AlignLeft}
                    label="Alinear a la izquierda"
                    onClick={() => format('justifyLeft')}
                    disabled={disabled}
                />
                <ToolbarBtn
                    icon={AlignCenter}
                    label="Centrar"
                    onClick={() => format('justifyCenter')}
                    disabled={disabled}
                />
                <ToolbarBtn
                    icon={ListOrdered}
                    label="Lista numerada"
                    onClick={() => format('insertOrderedList')}
                    disabled={disabled}
                />
                <ToolbarBtn
                    icon={List}
                    label="Lista con viñetas"
                    onClick={() => format('insertUnorderedList')}
                    disabled={disabled}
                />
                <ToolbarBtn
                    icon={ImageIcon}
                    label={logoUrl ? 'Insertar logo (centrado)' : 'Sube el logo en Configuración → General'}
                    onClick={insertLogo}
                    disabled={disabled}
                />
            </div>

            <div className="space-y-2 border-b border-primary/10 bg-sky-50/80 px-2 py-2 dark:bg-sky-950/20">
                {VAR_GROUPS.map((group) => (
                    <div key={group.label} className="flex flex-wrap items-center gap-1.5">
                        <span className="w-16 shrink-0 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                            {group.label}
                        </span>
                        {group.items.map((key) => (
                            <Button
                                key={key}
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={disabled}
                                className="h-6 cursor-pointer px-1.5 text-[11px]"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => insertVar(key)}
                            >
                                {key}
                            </Button>
                        ))}
                    </div>
                ))}
            </div>

            <div
                ref={ref}
                contentEditable={!disabled}
                suppressContentEditableWarning
                className={cn(
                    'auth-doc-editor min-h-[220px] max-h-[min(48vh,420px)] overflow-y-auto bg-[#fffcf6] px-4 py-3 text-sm leading-relaxed text-stone-800 outline-none',
                    disabled && 'pointer-events-none opacity-70',
                )}
                onInput={() => {
                    if (ref.current) {
                        onChange(ref.current.innerHTML);
                    }
                }}
                onPaste={(e) => {
                    e.preventDefault();
                    const text = e.clipboardData.getData('text/plain');
                    document.execCommand('insertText', false, text);
                    if (ref.current) {
                        onChange(ref.current.innerHTML);
                    }
                }}
            />
        </div>
    );
}

function ToolbarBtn({
    icon: Icon,
    label,
    onClick,
    disabled,
}: {
    icon: typeof Bold;
    label: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={disabled}
            className="size-8 cursor-pointer"
            title={label}
            aria-label={label}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
        >
            <Icon className="size-3.5" strokeWidth={2.25} />
        </Button>
    );
}
