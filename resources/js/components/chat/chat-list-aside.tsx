import { MessagesSquare, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ChatListAsideProps = {
    /** Título móvil del panel (ej. Conversaciones / Clínicas). */
    mobileTitle: string;
    mobileSubtitle?: string;
    /** Si hay hilo abierto, el drawer puede cerrarse. */
    hasActiveThread: boolean;
    mobileListOpen: boolean;
    onCloseMobileList?: () => void;
    /** Overlay detrás del drawer en móvil. */
    onBackdropClick?: () => void;
    toolbar?: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * Columna izquierda del chat: lista + drawer móvil (misma UX tenant / plataforma).
 */
export function ChatListAside({
    mobileTitle,
    mobileSubtitle,
    hasActiveThread,
    mobileListOpen,
    onCloseMobileList,
    onBackdropClick,
    toolbar,
    children,
    className,
}: ChatListAsideProps) {
    return (
        <>
            <button
                type="button"
                aria-label="Cerrar lista"
                tabIndex={mobileListOpen && hasActiveThread ? 0 : -1}
                onClick={onBackdropClick}
                className={cn(
                    'absolute inset-0 z-30 bg-slate-950/40 backdrop-blur-[3px] transition-opacity duration-500 lg:pointer-events-none lg:hidden',
                    hasActiveThread && mobileListOpen
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                )}
            />

            <aside
                className={cn(
                    'z-40 flex min-h-0 flex-col bg-muted/20 lg:relative lg:z-auto lg:translate-x-0 lg:border-r lg:border-border/60 lg:shadow-none',
                    'max-lg:absolute max-lg:inset-y-0 max-lg:left-0 max-lg:bg-card max-lg:transition-transform max-lg:duration-500 max-lg:ease-[cubic-bezier(0.22,1,0.36,1)] max-lg:will-change-transform',
                    !hasActiveThread &&
                        'max-lg:inset-0 max-lg:w-full max-lg:translate-x-0',
                    hasActiveThread &&
                        'max-lg:w-[min(20.5rem,82vw)] max-lg:border-r max-lg:border-border/50 max-lg:shadow-[12px_0_40px_-12px_rgba(15,23,42,0.35)]',
                    hasActiveThread &&
                        (mobileListOpen
                            ? 'max-lg:translate-x-0'
                            : 'max-lg:translate-x-[-105%]'),
                    className,
                )}
            >
                <div className="flex items-center gap-2 border-b border-border/50 bg-card px-3 py-3 lg:hidden">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700 dark:text-emerald-300">
                        <MessagesSquare className="size-3.5" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold">
                            {mobileTitle}
                        </p>
                        {mobileSubtitle ? (
                            <p className="truncate text-[10px] text-muted-foreground">
                                {mobileSubtitle}
                            </p>
                        ) : null}
                    </div>
                    {hasActiveThread && onCloseMobileList ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 shrink-0 cursor-pointer"
                            onClick={onCloseMobileList}
                            aria-label="Cerrar"
                        >
                            <X className="size-4" />
                        </Button>
                    ) : null}
                </div>

                {toolbar ? (
                    <div className="space-y-2 border-b border-border/50 p-3">
                        {toolbar}
                    </div>
                ) : null}

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    {children}
                </div>
            </aside>
        </>
    );
}
