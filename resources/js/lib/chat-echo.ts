type BroadcastConfig = {
    enabled: boolean;
    key: string | null;
    host?: string | null;
    port?: number | null;
    scheme?: string | null;
};

type ChatEchoChannel = {
    listen: (event: string, cb: (payload: unknown) => void) => ChatEchoChannel;
    stopListening?: (event: string) => void;
};

type ChatEchoInstance = {
    private: (ch: string) => ChatEchoChannel;
    leave?: (ch: string) => void;
    disconnect?: () => void;
};

function readXsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

function csrfHeaders(): Record<string, string> {
    const meta = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readXsrfToken(),
        ...(meta ? { 'X-CSRF-TOKEN': meta } : {}),
    };
}

async function loadChatEchoModules(): Promise<{
    EchoCtor: new (opts: Record<string, unknown>) => ChatEchoInstance;
    Pusher: unknown;
} | null> {
    try {
        const [echoMod, pusherMod] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);
        const EchoCtor =
            (echoMod as { default?: new (opts: Record<string, unknown>) => ChatEchoInstance })
                .default
            ?? (echoMod as new (opts: Record<string, unknown>) => ChatEchoInstance);
        const Pusher =
            (pusherMod as { default?: unknown }).default ?? pusherMod;

        return { EchoCtor, Pusher };
    } catch {
        return null;
    }
}

let sharedEcho: ChatEchoInstance | null = null;
let sharedEchoKey: string | null = null;

export async function getSharedChatEcho(
    broadcast: BroadcastConfig,
): Promise<ChatEchoInstance | null> {
    if (!broadcast.enabled || !broadcast.key) {
        return null;
    }

    const scheme = broadcast.scheme === 'http' ? 'http' : 'https';
    const port = Number(broadcast.port ?? (scheme === 'https' ? 443 : 8080));
    const host = broadcast.host || window.location.hostname;
    const cacheKey = `${broadcast.key}|${host}|${port}|${scheme}`;

    if (sharedEcho && sharedEchoKey === cacheKey) {
        return sharedEcho;
    }

    if (sharedEcho) {
        try {
            sharedEcho.disconnect?.();
        } catch {
            // ignore
        }
        sharedEcho = null;
        sharedEchoKey = null;
    }

    const mods = await loadChatEchoModules();
    if (!mods) {
        return null;
    }

    const { EchoCtor, Pusher } = mods;
    (window as unknown as { Pusher?: unknown }).Pusher = Pusher;

    sharedEcho = new EchoCtor({
        broadcaster: 'reverb',
        key: broadcast.key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrfHeaders(),
        },
    });
    sharedEchoKey = cacheKey;

    return sharedEcho;
}

export type { BroadcastConfig, ChatEchoInstance, ChatEchoChannel };
