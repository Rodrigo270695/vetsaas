import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const DEFAULT_PET_PHOTOS = [
    '/images/auth/pets/dog.svg',
    '/images/auth/pets/cat.svg',
    '/images/auth/pets/bunny.svg',
] as const;

const PHOTO_MOTION = [
    'left-[18%] top-8 z-[1] -rotate-12 translate-y-5 group-hover/folder:translate-y-[-2.8rem] group-hover/folder:-translate-x-2 group-hover/folder:-rotate-[18deg]',
    'left-1/2 top-6 z-[2] -translate-x-1/2 translate-y-6 group-hover/folder:translate-y-[-3.4rem]',
    'right-[18%] top-8 z-[1] rotate-12 translate-y-5 group-hover/folder:translate-y-[-2.8rem] group-hover/folder:translate-x-2 group-hover/folder:rotate-[18deg]',
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

function FolderGlyph({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 88 68"
            className={cn('drop-shadow-md', className)}
            aria-hidden
        >
            <path
                d="M8 18c0-3.3 2.7-6 6-6h18l6 7h36c3.3 0 6 2.7 6 6v31c0 3.3-2.7 6-6 6H14c-3.3 0-6-2.7-6-6V18z"
                fill="#F5A524"
            />
            <path
                d="M8 28h72v28c0 3.3-2.7 6-6 6H14c-3.3 0-6-2.7-6-6V28z"
                fill="#E8940C"
            />
        </svg>
    );
}

function CabinetGlyph({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 40 48" className={cn('drop-shadow-sm', className)} aria-hidden>
            <rect x="4" y="2" width="32" height="44" rx="6" fill="#3F3F46" />
            <rect x="8" y="8" width="24" height="10" rx="2" fill="#27272A" />
            <rect x="8" y="22" width="24" height="10" rx="2" fill="#27272A" />
            <rect x="18" y="11" width="6" height="3" rx="1" fill="#71717A" />
            <rect x="18" y="25" width="6" height="3" rx="1" fill="#71717A" />
        </svg>
    );
}

/**
 * Folder tipo Aceternity: al hover salen 3 fotos (pacientes del tenant o mascotas default).
 */
export function AuthPetFolderCard({ className }: { className?: string }) {
    const photos = resolvePetPhotos(usePage().props.auth_pet_photos);

    return (
        <div
            className={cn(
                'group/folder pointer-events-auto absolute w-64 rounded-2xl border border-border/60 bg-card/85 p-4 text-left shadow-[0_20px_60px_-30px_rgba(0,40,30,0.35)] backdrop-blur-xl dark:bg-card/60',
                className,
            )}
        >
            <div className="relative mx-auto h-28 w-full">
                <div
                    aria-hidden
                    className="absolute inset-x-6 top-10 h-px bg-[repeating-linear-gradient(90deg,transparent,transparent_4px,var(--border)_4px,var(--border)_8px)] opacity-50"
                />
                {photos.map((src, index) => (
                    <img
                        key={`${src}-${index}`}
                        src={src}
                        alt=""
                        className={cn(
                            'absolute h-14 w-14 rounded-xl object-cover shadow-lg ring-2 ring-white transition-transform duration-500 ease-out dark:ring-zinc-800',
                            PHOTO_MOTION[index],
                        )}
                    />
                ))}
                <div className="absolute bottom-0 left-1/2 z-[3] flex -translate-x-[58%] items-end gap-1">
                    <FolderGlyph className="h-14 w-[4.4rem] transition-transform duration-500 group-hover/folder:-translate-y-0.5" />
                    <CabinetGlyph className="mb-0.5 h-11 w-9 opacity-90" />
                </div>
            </div>
            <p className="mt-3 text-sm font-semibold text-foreground">Historias clínicas</p>
            <p className="text-xs leading-relaxed text-muted-foreground">
                Fichas y fotos de tus pacientes, listas al iniciar sesión.
            </p>
        </div>
    );
}
