import { Skeleton } from '@/components/ui/skeleton';

/** Placeholder del área principal solo en el primer ingreso de sesión. */
export function SessionEnterSkeleton() {
    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="space-y-2">
                <Skeleton className="h-8 w-56 max-w-full" />
                <Skeleton className="h-4 w-80 max-w-full" />
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-24 rounded-2xl" />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-64 rounded-2xl" />
                <Skeleton className="h-64 rounded-2xl" />
            </div>
            <Skeleton className="h-40 rounded-2xl" />
        </div>
    );
}
