import { Maximize2 } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AgendaDayTimeline } from './agenda-day-timeline';
import type { AgendaCalendarLabels, AgendaEvent } from './agenda-types';
import type { DragEvent } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dateKey: string;
    dayLabel: string;
    events: readonly AgendaEvent[];
    hourSlots: readonly number[];
    timeZone: string;
    labels: AgendaCalendarLabels;
    canCreate: boolean;
    canUpdate: boolean;
    dragOverKey: string | null;
    onSelectEvent: (event: AgendaEvent) => void;
    onScheduleAt: (dateKey: string, hourLabel: string) => void;
    onDragStart: (e: DragEvent, event: AgendaEvent) => void;
    onDragEnd: () => void;
    onAllowDrop: (e: DragEvent, key: string) => void;
    onClearDragOver: (key: string) => void;
    onDropOnHour: (e: DragEvent, dateKey: string, hour: number) => void;
    isSlotPast: (dateKey: string, hourLabel: string) => boolean;
};

export function AgendaDayExpandModal({
    open,
    onOpenChange,
    dateKey,
    dayLabel,
    events,
    hourSlots,
    timeZone,
    labels,
    canCreate,
    canUpdate,
    dragOverKey,
    onSelectEvent,
    onScheduleAt,
    onDragStart,
    onDragEnd,
    onAllowDrop,
    onClearDragOver,
    onDropOnHour,
    isSlotPast,
}: Props) {
    const durationMin =
        labels.durationMin ?? ((m: number) => `${m} min`);
    const until = labels.until ?? '→';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'flex max-h-[min(92vh,56rem)] w-[min(100%,42rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl',
                    'rounded-2xl border-border/60 shadow-2xl shadow-black/20 ring-1 ring-black/5',
                    'dark:shadow-black/50 dark:ring-white/10',
                    // Apertura tipo Apple: blur + scale + slide suave
                    'data-[state=open]:animate-in data-[state=closed]:animate-out',
                    'data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0',
                    'data-[state=open]:zoom-in-90 data-[state=closed]:zoom-out-95',
                    'data-[state=open]:slide-in-from-bottom-3 data-[state=closed]:slide-out-to-bottom-2',
                    'data-[state=open]:duration-500 data-[state=closed]:duration-200',
                    'data-[state=open]:ease-[cubic-bezier(0.16,1,0.3,1)]',
                    'data-[state=closed]:ease-[cubic-bezier(0.4,0,1,1)]',
                )}
            >
                <DialogHeader className="shrink-0 space-y-1 border-b border-border/50 bg-gradient-to-b from-muted/40 to-background px-5 py-4 pr-12 text-left">
                    <DialogTitle className="text-base font-semibold tracking-tight capitalize">
                        {labels.expandDayTitle ?? labels.dayAgenda}
                        <span className="mt-0.5 block text-sm font-medium text-muted-foreground normal-case">
                            {dayLabel}
                        </span>
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        {events.length === 0
                            ? labels.dayEmpty
                            : labels.dayCount(events.length)}
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                    <AgendaDayTimeline
                        dateKey={dateKey}
                        events={events}
                        hourSlots={hourSlots}
                        timeZone={timeZone}
                        labels={{
                            scheduleAt: labels.scheduleAt,
                            durationMin,
                            until,
                            now: labels.now,
                        }}
                        canCreate={canCreate}
                        canUpdate={canUpdate}
                        pxPerHour={96}
                        compact={false}
                        showNowLine
                        dragOverKey={dragOverKey}
                        onSelectEvent={(event) => {
                            onSelectEvent(event);
                            onOpenChange(false);
                        }}
                        onScheduleAt={(dk, hour) => {
                            onScheduleAt(dk, hour);
                            onOpenChange(false);
                        }}
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                        onAllowDrop={onAllowDrop}
                        onClearDragOver={onClearDragOver}
                        onDropOnHour={onDropOnHour}
                        isSlotPast={isSlotPast}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function AgendaDayExpandButton({
    label,
    onClick,
    className,
}: {
    label: string;
    onClick: () => void;
    className?: string;
}) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClick}
            className={cn(
                'h-8 cursor-pointer gap-1.5 rounded-lg border-border/60 bg-background/80 text-xs shadow-xs',
                'hover:border-primary/40 hover:bg-primary/5',
                className,
            )}
        >
            <Maximize2 className="size-3.5" aria-hidden />
            {label}
        </Button>
    );
}
