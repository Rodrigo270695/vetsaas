import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    url: string | null;
    name?: string | null;
    title?: string;
    onOpenChange: (open: boolean) => void;
};

export function ChatImageLightbox({
    open,
    url,
    name,
    title = 'Imagen',
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[95dvh] max-w-[95vw] overflow-hidden border-0 bg-black/95 p-2 sm:max-w-4xl">
                <DialogHeader className="sr-only">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{name ?? title}</DialogDescription>
                </DialogHeader>
                {url ? (
                    <img
                        src={url}
                        alt={name ?? title}
                        className="mx-auto max-h-[85dvh] w-auto max-w-full object-contain"
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
