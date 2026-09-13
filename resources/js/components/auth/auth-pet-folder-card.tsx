import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const DEFAULT_PET_PHOTOS = [
    '/images/auth/pets/dog.svg',
    '/images/auth/pets/cat.svg',
    '/images/auth/pets/bunny.svg',
] as const;

function resolvePetPhotos(fromTenant: unknown): string[] {
    const urls = Array.isArray(fromTenant)
        ? fromTenant.filter((item): item is string => typeof item === 'string' && item !== '')
        : [];
    const out = urls.slice(0, 3);
    let i = 0;
    while (out.length < 3) {
        out.push(DEFAULT_PET_PHOTOS[i % DEFAULT_PET_PHOTOS.length] ?? DEFAULT_PET_PHOTOS[0]);
        i += 1;
    }

    return out;
}

const PHOTO_REST =
    'absolute bottom-7 left-1/2 z-10 h-10 w-10 rounded-lg object-cover shadow-md ring-2 ring-white/90 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] dark:ring-zinc-900';

/**
 * Carpeta: las fotos viven detrás de la tapa y, al hover, salen como fichas.
 */
export function AuthPetFolderCard({ className }: { className?: string }) {
    const photos = resolvePetPhotos(usePage().props.auth_pet_photos);

    return (
        <div
            className={cn(
                'group/folder pointer-events-auto absolute w-64 rounded-2xl border border-border/60 bg-card/90 p-4 text-left shadow-[0_20px_60px_-30px_rgba(0,40,30,0.35)] backdrop-blur-xl dark:bg-card/60',
                className,
            )}
        >
            <div className="relative mx-auto h-28 w-[6.75rem]">
                {/* Dorso + pestaña */}
                <div className="absolute inset-x-0 bottom-0 z-0 h-[3.15rem] rounded-md bg-amber-400 shadow-[inset_0_1px_0_rgb(255_255_255/0.35)]">
                    <div className="absolute -top-2.5 left-2 h-2.5 w-8 rounded-t-[5px] bg-amber-400" />
                </div>

                {photos[0] ? (
                    <img
                        src={photos[0]}
                        alt=""
                        className={cn(
                            PHOTO_REST,
                            '-translate-x-[70%] translate-y-4 -rotate-6',
                            'group-hover/folder:-translate-x-[115%] group-hover/folder:-translate-y-12 group-hover/folder:-rotate-[16deg]',
                        )}
                    />
                ) : null}
                {photos[1] ? (
                    <img
                        src={photos[1]}
                        alt=""
                        className={cn(
                            PHOTO_REST,
                            'z-[11] -translate-x-1/2 translate-y-5 delay-75',
                            'group-hover/folder:-translate-y-[3.55rem] group-hover/folder:rotate-0',
                        )}
                    />
                ) : null}
                {photos[2] ? (
                    <img
                        src={photos[2]}
                        alt=""
                        className={cn(
                            PHOTO_REST,
                            '-translate-x-[30%] translate-y-4 rotate-6 delay-150',
                            'group-hover/folder:translate-x-[15%] group-hover/folder:-translate-y-12 group-hover/folder:rotate-[16deg]',
                        )}
                    />
                ) : null}

                {/* Tapa frontal: cubre las fotos “guardadas” */}
                <div className="absolute inset-x-0 bottom-0 z-20 h-[2.55rem] rounded-md bg-linear-to-b from-amber-400 to-amber-500 shadow-[0_-4px_12px_-6px_rgb(0_0_0/0.25)] transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] group-hover/folder:translate-y-px" />
            </div>
            <p className="mt-1 text-sm font-semibold text-foreground">Historias clínicas</p>
            <p className="text-xs leading-relaxed text-muted-foreground">
                Fichas y fotos de tus pacientes, listas al iniciar sesión.
            </p>
        </div>
    );
}
