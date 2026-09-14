import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { MoreHorizontal } from 'lucide-react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function AncestorCrumb({ item }: { item: BreadcrumbItemType }) {
    if (item.href) {
        return (
            <BreadcrumbLink asChild>
                <Link href={item.href} className="truncate">
                    {item.title}
                </Link>
            </BreadcrumbLink>
        );
    }

    return (
        <span className="truncate text-muted-foreground select-none">
            {item.title}
        </span>
    );
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    if (breadcrumbs.length === 0) {
        return null;
    }

    const ancestors = breadcrumbs.slice(0, -1);
    const current = breadcrumbs[breadcrumbs.length - 1];

    return (
        <Breadcrumb className="min-w-0 max-w-full">
            <BreadcrumbList className="flex-nowrap gap-1 overflow-hidden sm:gap-1.5">
                {ancestors.length > 0 ? (
                    <BreadcrumbItem className="md:hidden">
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className="flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label="Ruta de navegación"
                            >
                                <MoreHorizontal className="size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="max-w-64">
                                {ancestors.map((item, index) =>
                                    item.href ? (
                                        <DropdownMenuItem key={index} asChild>
                                            <Link href={item.href} className="truncate">
                                                {item.title}
                                            </Link>
                                        </DropdownMenuItem>
                                    ) : (
                                        <DropdownMenuItem key={index} disabled>
                                            <span className="truncate">{item.title}</span>
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </BreadcrumbItem>
                ) : null}

                {ancestors.map((item, index) => (
                    <Fragment key={`${item.title}-${index}`}>
                        <BreadcrumbItem className="hidden max-w-36 min-w-0 md:inline-flex">
                            <AncestorCrumb item={item} />
                        </BreadcrumbItem>
                        <BreadcrumbSeparator className="hidden md:flex" />
                    </Fragment>
                ))}

                {ancestors.length > 0 ? (
                    <BreadcrumbSeparator className="md:hidden" />
                ) : null}

                <BreadcrumbItem className="min-w-0">
                    <BreadcrumbPage className="block truncate font-medium">
                        {current.title}
                    </BreadcrumbPage>
                </BreadcrumbItem>
            </BreadcrumbList>
        </Breadcrumb>
    );
}
