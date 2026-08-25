import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

type PlatformSupportChatUnreadContextValue = {
    unreadTotal: number;
    setUnreadTotal: (n: number) => void;
    activeTenantId: string | null;
    setActiveTenantId: (id: string | null) => void;
};

const PlatformSupportChatUnreadContext =
    createContext<PlatformSupportChatUnreadContextValue | null>(null);

export function PlatformSupportChatUnreadProvider({
    children,
    initialUnread = 0,
}: {
    children: ReactNode;
    initialUnread?: number;
}) {
    const [unreadTotal, setUnreadTotal] = useState(initialUnread);
    const [activeTenantId, setActiveTenantId] = useState<string | null>(null);

    const setUnread = useCallback((n: number) => {
        setUnreadTotal(Math.max(0, Math.floor(n)));
    }, []);

    const value = useMemo(
        () => ({
            unreadTotal,
            setUnreadTotal: setUnread,
            activeTenantId,
            setActiveTenantId,
        }),
        [unreadTotal, setUnread, activeTenantId],
    );

    return (
        <PlatformSupportChatUnreadContext.Provider value={value}>
            {children}
        </PlatformSupportChatUnreadContext.Provider>
    );
}

export function usePlatformSupportChatUnread(): PlatformSupportChatUnreadContextValue {
    const ctx = useContext(PlatformSupportChatUnreadContext);
    if (!ctx) {
        return {
            unreadTotal: 0,
            setUnreadTotal: () => undefined,
            activeTenantId: null,
            setActiveTenantId: () => undefined,
        };
    }

    return ctx;
}
