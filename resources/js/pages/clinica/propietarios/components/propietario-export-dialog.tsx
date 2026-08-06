import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export type PropietarioExportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** URL base de export (ya con filtros). Se le agrega include_mascotas si aplica. */
    exportUrl: string;
};

export function PropietarioExportDialog({
    open,
    onOpenChange,
    exportUrl,
}: PropietarioExportDialogProps) {
    const { t } = useTranslation(['propietarios', 'common']);
    const [includeMascotas, setIncludeMascotas] = useState(false);

    useEffect(() => {
        if (open) {
            setIncludeMascotas(false);
        }
    }, [open]);

    const handleDownload = () => {
        const url = new URL(exportUrl, window.location.origin);
        if (includeMascotas) {
            url.searchParams.set('include_mascotas', '1');
        } else {
            url.searchParams.delete('include_mascotas');
        }
        window.location.href = url.toString();
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('export.title')}</DialogTitle>
                    <DialogDescription>{t('export.description')}</DialogDescription>
                </DialogHeader>

                <div className="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/30 px-3 py-3">
                    <Checkbox
                        id="export-include-mascotas"
                        checked={includeMascotas}
                        onCheckedChange={(checked) =>
                            setIncludeMascotas(checked === true)
                        }
                        className="mt-0.5"
                    />
                    <div className="space-y-1">
                        <Label
                            htmlFor="export-include-mascotas"
                            className="cursor-pointer text-sm font-medium leading-none"
                        >
                            {t('export.include_pets')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('export.include_pets_hint')}
                        </p>
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={handleDownload}
                        className="cursor-pointer gap-2"
                    >
                        <Download className="size-4" strokeWidth={2.5} />
                        {t('export.download')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
