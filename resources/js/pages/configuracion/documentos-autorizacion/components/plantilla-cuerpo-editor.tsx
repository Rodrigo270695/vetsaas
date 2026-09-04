import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    Bold,
    ChevronDown,
    ImageIcon,
    Italic,
    List,
    ListOrdered,
    Underline,
} from 'lucide-react';
import { useEffect, useRef, useState, type CSSProperties } from 'react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const FONTS = [
    { label: 'Arial', value: 'Arial' },
    { label: 'Times', value: 'Times New Roman' },
    { label: 'Georgia', value: 'Georgia' },
    { label: 'Courier', value: 'Courier New' },
] as const;

const FONT_SIZES = [
    { label: '12', value: '2' },
    { label: '14', value: '3' },
    { label: '18', value: '4' },
    { label: '24', value: '5' },
    { label: '32', value: '6' },
] as const;

const VAR_GROUPS: readonly { label: string; items: readonly string[] }[] = [
    { label: 'Paciente', items: ['paciente', 'especie', 'raza', 'edad', 'sexo'] },
    { label: 'Titular', items: ['propietario', 'documento', 'telefono'] },
    { label: 'Clínica', items: ['clinica', 'ciudad', 'veterinario', 'logo'] },
    { label: 'Consulta', items: ['motivo'] },
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
    const savedRange = useRef<Range | null>(null);
    const [openVarGroup, setOpenVarGroup] = useState<string | null>(null);

    const rememberSelection = () => {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) {
            return;
        }
        const node = sel.anchorNode;
        if (node && ref.current?.contains(node)) {
            savedRange.current = sel.getRangeAt(0).cloneRange();
        }
    };

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

    useEffect(() => {
        setOpenVarGroup(null);
    }, [resetKey]);

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

    const format = (cmd: string, arg?: string) => {
        const el = ref.current;
        if (!el || disabled) {
            return;
        }
        el.focus();
        run(cmd, arg);
        onChange(el.innerHTML);
    };

    const formatFromMenu = (cmd: string, arg: string) => {
        const el = ref.current;
        if (!el || disabled) {
            return;
        }
        el.focus();
        const sel = window.getSelection();
        if (sel && savedRange.current) {
            sel.removeAllRanges();
            sel.addRange(savedRange.current);
        }
        run(cmd, arg);
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
                    icon={AlignJustify}
                    label="Justificar"
                    onClick={() => format('justifyFull')}
                    disabled={disabled}
                />
                <FormatMenu
                    label="Fuente"
                    disabled={disabled}
                    options={FONTS.map((font) => ({
                        label: font.label,
                        value: font.value,
                        style: { fontFamily: font.value },
                    }))}
                    onOpen={rememberSelection}
                    onPick={(value) => formatFromMenu('fontName', value)}
                />
                <FormatMenu
                    label="Tamaño"
                    disabled={disabled}
                    options={FONT_SIZES.map((size) => ({
                        label: size.label,
                        value: size.value,
                    }))}
                    onOpen={rememberSelection}
                    onPick={(value) => formatFromMenu('fontSize', value)}
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

            <div className="border-b border-primary/10 bg-sky-50/80 px-2 py-1.5 dark:bg-sky-950/20">
                <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    Variables
                </p>
                <div className="flex flex-wrap gap-1">
                    {VAR_GROUPS.map((group) => {
                        const isOpen = openVarGroup === group.label;

                        return (
                            <Button
                                key={group.label}
                                type="button"
                                variant={isOpen ? 'default' : 'outline'}
                                size="sm"
                                disabled={disabled}
                                aria-expanded={isOpen}
                                className="h-7 cursor-pointer gap-1 px-2 text-[11px] font-medium"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() =>
                                    setOpenVarGroup((current) =>
                                        current === group.label ? null : group.label,
                                    )
                                }
                            >
                                {group.label}
                                <ChevronDown
                                    className={cn(
                                        'size-3.5 opacity-70 transition-transform',
                                        isOpen && 'rotate-180',
                                    )}
                                />
                            </Button>
                        );
                    })}
                </div>
                {openVarGroup ? (
                    <div className="mt-1.5 flex flex-wrap gap-1 rounded-md border border-primary/10 bg-background/80 p-1.5">
                        {VAR_GROUPS.find((group) => group.label === openVarGroup)?.items.map((key) => (
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
                                {`{{${key}}}`}
                            </Button>
                        ))}
                    </div>
                ) : null}
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

function FormatMenu({
    label,
    options,
    disabled,
    onOpen,
    onPick,
}: {
    label: string;
    options: { label: string; value: string; style?: CSSProperties }[];
    disabled?: boolean;
    onOpen: () => void;
    onPick: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Popover
            modal={false}
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    onOpen();
                }
                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    className="h-8 cursor-pointer gap-1 px-2 text-xs font-normal"
                    aria-label={label}
                    onMouseDown={(e) => {
                        e.preventDefault();
                        onOpen();
                    }}
                >
                    {label}
                    <ChevronDown className="size-3.5 opacity-70" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                sideOffset={4}
                className="z-80 w-40 p-1 pointer-events-auto"
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
            >
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        className="flex w-full cursor-pointer rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                        style={option.style}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                            onPick(option.value);
                            setOpen(false);
                        }}
                    >
                        {option.label}
                    </button>
                ))}
            </PopoverContent>
        </Popover>
    );
}
