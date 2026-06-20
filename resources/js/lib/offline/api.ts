function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

export async function offlineFetchJson<T>(
    url: string,
    init: RequestInit = {},
): Promise<T> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
            ...(init.body ? { 'Content-Type': 'application/json' } : {}),
            ...(init.headers ?? {}),
        },
        ...init,
    });

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json() as Promise<T>;
}

export async function fetchOfflineBootstrap(
    scope: 'caja' | 'clinica' | 'inventario' | 'servicios' | 'configuracion' | 'all',
): Promise<unknown> {
    const res = await offlineFetchJson<{ data: unknown }>(`/offline/bootstrap?scope=${scope}`);

    return res.data;
}

/** @deprecated Usar fetchOfflineBootstrap('caja') */
export async function fetchCajaBootstrap(): Promise<unknown> {
    return fetchOfflineBootstrap('caja');
}

/** @deprecated Usar fetchOfflineBootstrap('clinica') */
export async function fetchClinicaBootstrap(): Promise<unknown> {
    return fetchOfflineBootstrap('clinica');
}

export async function fetchInventarioBootstrap(): Promise<unknown> {
    return fetchOfflineBootstrap('inventario');
}

export async function fetchServiciosBootstrap(): Promise<unknown> {
    return fetchOfflineBootstrap('servicios');
}

export async function fetchConfiguracionBootstrap(): Promise<unknown> {
    return fetchOfflineBootstrap('configuracion');
}
