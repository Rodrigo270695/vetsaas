import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import Heading from '@/components/heading';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';

type PlaceholderPageProps = {
    title: string;
    description?: string;
};

/**
 * Página placeholder para módulos aún sin implementar.
 * Muestra el título + descripción + un patrón visual y un badge "En construcción".
 *
 * Cuando construyamos el CRUD de un módulo, esta página se reemplaza por la
 * vista real (lista, formularios, etc.).
 */
export default function PlaceholderPage({
    title,
    description,
}: PlaceholderPageProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={title} description={description} />
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning">
                        <Construction
                            className="size-3.5"
                            strokeWidth={2.25}
                        />
                        En construcción
                    </span>
                </div>

                <div className="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/15 dark:stroke-neutral-100/15" />
                    <div className="absolute inset-0 flex items-center justify-center">
                        <div className="rounded-2xl border border-border/60 bg-card/70 px-6 py-4 text-center text-sm text-muted-foreground shadow-sm backdrop-blur">
                            Próximamente podrás gestionar{' '}
                            <span className="font-medium text-foreground">
                                {title.toLowerCase()}
                            </span>{' '}
                            desde aquí.
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
