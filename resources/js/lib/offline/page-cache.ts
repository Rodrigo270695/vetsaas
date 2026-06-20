import { router } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';

import { idbGet, idbSet, isIndexedDbSupported } from './idb';
import { isOfflinePath, normalizeOfflinePath } from './offline-routes';

const PAGE_CACHE_PREFIX = 'inertia:';

type CachedInertiaPage = {
    url: string;
    saved_at: string;
    page: Page;
};

export function rememberInertiaPage(page: Page): void {
    if (!isIndexedDbSupported() || !isOfflinePath(page.url)) {
        return;
    }

    const key = PAGE_CACHE_PREFIX + normalizeOfflinePath(page.url);

    void idbSet<CachedInertiaPage>(key, {
        url: page.url,
        saved_at: new Date().toISOString(),
        page,
    });
}

export async function loadCachedInertiaPage(
    url: string,
): Promise<Page | null> {
    if (!isIndexedDbSupported()) {
        return null;
    }

    const key = PAGE_CACHE_PREFIX + normalizeOfflinePath(url);
    const row = await idbGet<CachedInertiaPage>(key);

    return row?.page ?? null;
}

export async function visitOfflineAware(
    href: string,
    options?: Parameters<typeof router.visit>[1],
): Promise<boolean> {
    if (navigator.onLine) {
        router.visit(href, options);

        return true;
    }

    const cached = await loadCachedInertiaPage(href);

    if (!cached) {
        return false;
    }

    router.replace({
        component: cached.component,
        url: cached.url,
        props: cached.props,
        preserveScroll: options?.preserveScroll ?? true,
        preserveState: options?.preserveState ?? false,
    });

    return true;
}
