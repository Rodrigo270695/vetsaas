import { Loader2, StickyNote, Trash2, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type SupportAgent = { id: string; name: string };
export type SupportNote = {
    id: string;
    body: string;
    user_id: string;
    user_name: string;
    created_at: string | null;
};
export type SupportTemplate = {
    id: string;
    label: string;
    body: string;
    sort_order?: number;
    is_active?: boolean;
};

type NotesProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    loading: boolean;
    notes: SupportNote[];
    draft: string;
    onDraftChange: (v: string) => void;
    onSubmit: () => void;
    onDelete: (id: string) => void;
    submitting: boolean;
    canManage: boolean;
    labels: {
        title: string;
        hint: string;
        placeholder: string;
        save: string;
        empty: string;
        cancel: string;
    };
};

export function SupportNotesDialog({
    open,
    onOpenChange,
    loading,
    notes,
    draft,
    onDraftChange,
    onSubmit,
    onDelete,
    submitting,
    canManage,
    labels,
}: NotesProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <DialogHeader className="border-b border-border/60 px-5 py-4">
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <StickyNote className="size-4" />
                        {labels.title}
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        {labels.hint}
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-64 space-y-2 overflow-y-auto p-3">
                    {loading ? (
                        <p className="flex justify-center gap-2 py-8 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                        </p>
                    ) : notes.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            {labels.empty}
                        </p>
                    ) : (
                        notes.map((n) => (
                            <div
                                key={n.id}
                                className="rounded-lg border border-border/50 bg-muted/30 px-3 py-2"
                            >
                                <div className="mb-1 flex items-center gap-2">
                                    <span className="text-[10px] font-semibold text-amber-800 dark:text-amber-200">
                                        {n.user_name}
                                    </span>
                                    {canManage ? (
                                        <button
                                            type="button"
                                            className="ml-auto text-muted-foreground hover:text-destructive"
                                            onClick={() => onDelete(n.id)}
                                            aria-label={labels.cancel}
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    ) : null}
                                </div>
                                <p className="whitespace-pre-wrap text-xs">
                                    {n.body}
                                </p>
                            </div>
                        ))
                    )}
                </div>
                {canManage ? (
                    <div className="space-y-2 border-t border-border/60 p-3">
                        <Textarea
                            value={draft}
                            onChange={(e) => onDraftChange(e.target.value)}
                            placeholder={labels.placeholder}
                            rows={3}
                        />
                        <DialogFooter className="gap-2 sm:gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                {labels.cancel}
                            </Button>
                            <Button
                                type="button"
                                className="bg-emerald-600 hover:bg-emerald-700"
                                disabled={submitting || !draft.trim()}
                                onClick={onSubmit}
                            >
                                {submitting ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : null}
                                {labels.save}
                            </Button>
                        </DialogFooter>
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

type AssignProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    agents: SupportAgent[];
    value: string;
    onChange: (id: string) => void;
    onSave: () => void;
    saving: boolean;
    labels: {
        title: string;
        hint: string;
        none: string;
        save: string;
        cancel: string;
    };
};

export function SupportAssignDialog({
    open,
    onOpenChange,
    agents,
    value,
    onChange,
    onSave,
    saving,
    labels,
}: AssignProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <UserRound className="size-4" />
                        {labels.title}
                    </DialogTitle>
                    <DialogDescription>{labels.hint}</DialogDescription>
                </DialogHeader>
                <div className="max-h-64 space-y-1 overflow-y-auto">
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        className={cn(
                            'flex w-full rounded-lg px-3 py-2 text-left text-sm',
                            value === ''
                                ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                : 'hover:bg-muted/60',
                        )}
                    >
                        {labels.none}
                    </button>
                    {agents.map((a) => (
                        <button
                            key={a.id}
                            type="button"
                            onClick={() => onChange(a.id)}
                            className={cn(
                                'flex w-full rounded-lg px-3 py-2 text-left text-sm',
                                value === a.id
                                    ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                    : 'hover:bg-muted/60',
                            )}
                        >
                            {a.name}
                        </button>
                    ))}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {labels.cancel}
                    </Button>
                    <Button
                        type="button"
                        className="bg-emerald-600 hover:bg-emerald-700"
                        disabled={saving}
                        onClick={onSave}
                    >
                        {saving ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : null}
                        {labels.save}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type TemplatesProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templates: SupportTemplate[];
    canManage: boolean;
    onUse: (body: string) => void;
    onReload: () => void;
    onSave: (payload: {
        id?: string;
        label: string;
        body: string;
    }) => Promise<void>;
    onDelete: (id: string) => Promise<void>;
    labels: {
        title: string;
        hint: string;
        use: string;
        new: string;
        label: string;
        body: string;
        save: string;
        cancel: string;
        delete: string;
        empty: string;
    };
};

export function SupportTemplatesDialog({
    open,
    onOpenChange,
    templates,
    canManage,
    onUse,
    onSave,
    onDelete,
    labels,
}: TemplatesProps) {
    const [editing, setEditing] = useState<{
        id?: string;
        label: string;
        body: string;
    } | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) setEditing(null);
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="border-b border-border/60 px-5 py-4">
                    <DialogTitle className="text-base">{labels.title}</DialogTitle>
                    <DialogDescription className="text-xs">
                        {labels.hint}
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-[60dvh] space-y-2 overflow-y-auto p-3">
                    {templates.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            {labels.empty}
                        </p>
                    ) : (
                        templates.map((tpl) => (
                            <div
                                key={tpl.id}
                                className="rounded-lg border border-border/50 px-3 py-2"
                            >
                                <div className="mb-1 flex items-center gap-2">
                                    <span className="text-sm font-medium">
                                        {tpl.label}
                                    </span>
                                    <div className="ml-auto flex gap-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="h-7 bg-emerald-600 text-xs hover:bg-emerald-700"
                                            onClick={() => {
                                                onUse(tpl.body);
                                                onOpenChange(false);
                                            }}
                                        >
                                            {labels.use}
                                        </Button>
                                        {canManage
                                        && !tpl.id.startsWith('builtin-') ? (
                                            <>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 text-xs"
                                                    onClick={() =>
                                                        setEditing({
                                                            id: tpl.id,
                                                            label: tpl.label,
                                                            body: tpl.body,
                                                        })
                                                    }
                                                >
                                                    Editar
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 text-xs text-destructive"
                                                    onClick={() =>
                                                        void onDelete(tpl.id)
                                                    }
                                                >
                                                    {labels.delete}
                                                </Button>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                    {tpl.body}
                                </p>
                            </div>
                        ))
                    )}
                </div>
                {canManage ? (
                    <div className="border-t border-border/60 p-3">
                        {editing ? (
                            <div className="space-y-2">
                                <input
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    value={editing.label}
                                    onChange={(e) =>
                                        setEditing({
                                            ...editing,
                                            label: e.target.value,
                                        })
                                    }
                                    placeholder={labels.label}
                                />
                                <Textarea
                                    value={editing.body}
                                    onChange={(e) =>
                                        setEditing({
                                            ...editing,
                                            body: e.target.value,
                                        })
                                    }
                                    placeholder={labels.body}
                                    rows={4}
                                />
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditing(null)}
                                    >
                                        {labels.cancel}
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={saving}
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        onClick={() => {
                                            setSaving(true);
                                            void onSave(editing)
                                                .then(() => setEditing(null))
                                                .finally(() =>
                                                    setSaving(false),
                                                );
                                        }}
                                    >
                                        {labels.save}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={() =>
                                    setEditing({ label: '', body: '' })
                                }
                            >
                                {labels.new}
                            </Button>
                        )}
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
