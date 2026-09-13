import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
    LEGAL_DOC_ORDER,
    LEGAL_DOCS,
    type LegalDocId,
} from '@/components/legal/legal-docs';

type LegalModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    docId: LegalDocId;
    onDocIdChange: (id: LegalDocId) => void;
};

export function LegalModal({ open, onOpenChange, docId, onDocIdChange }: LegalModalProps) {
    const doc = LEGAL_DOCS[docId];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[min(88vh,720px)] max-w-[calc(100%-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <DialogHeader className="border-b border-border/60 px-5 py-4 pr-12 text-left">
                    <DialogTitle>{doc.title}</DialogTitle>
                    <DialogDescription>
                        VetSaaS · Perú · Actualizado el {doc.updated}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex min-h-0 flex-1 flex-col sm:flex-row">
                    <nav
                        aria-label="Documentos legales"
                        className="flex shrink-0 gap-1 overflow-x-auto border-b border-border/60 p-2 sm:w-44 sm:flex-col sm:overflow-y-auto sm:border-r sm:border-b-0 sm:p-3"
                    >
                        {LEGAL_DOC_ORDER.map((id) => (
                            <button
                                key={id}
                                type="button"
                                onClick={() => onDocIdChange(id)}
                                className={cn(
                                    'cursor-pointer rounded-md px-2.5 py-1.5 text-left text-xs font-medium whitespace-nowrap transition-colors',
                                    id === docId
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {LEGAL_DOCS[id].label}
                            </button>
                        ))}
                    </nav>
                    <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <div className="space-y-3 text-sm leading-relaxed text-foreground/90">
                            {doc.blocks.map((block, index) =>
                                block.type === 'p' ? (
                                    <p key={`${doc.id}-p-${index}`}>{block.text}</p>
                                ) : (
                                    <ul
                                        key={`${doc.id}-ul-${index}`}
                                        className="list-disc space-y-1.5 pl-4"
                                    >
                                        {block.items.map((item) => (
                                            <li key={item.slice(0, 48)}>{item}</li>
                                        ))}
                                    </ul>
                                ),
                            )}
                        </div>
                        <p className="mt-6 text-[0.7rem] leading-relaxed text-muted-foreground">
                            Este texto informa el uso del servicio y el marco de la Ley 29733. No
                            reemplaza asesoría legal personalizada ni el registro de bancos de datos
                            que la clínica deba cumplir ante la ANPDP.
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function useLegalModal() {
    const [open, setOpen] = useState(false);
    const [docId, setDocId] = useState<LegalDocId>('terminos');

    const openDoc = (id: LegalDocId) => {
        setDocId(id);
        setOpen(true);
    };

    return { open, setOpen, docId, setDocId, openDoc };
}
