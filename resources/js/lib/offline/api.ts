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

export async function fetchCajaBootstrap(): Promise<unknown> {
    const res = await offlineFetchJson<{ data: unknown }>('/caja/offline/bootstrap?scope=caja');

    return res.data;
}
