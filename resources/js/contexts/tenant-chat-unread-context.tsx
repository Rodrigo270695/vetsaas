import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

type TenantChatUnreadContextValue = {
    unreadTotal: number;
    setUnreadTotal: (n: number) => void;
    activeConversationId: string | null;
    setActiveConversationId: (id: string | null) => void;
};

const TenantChatUnreadContext = createContext<TenantChatUnreadContextValue | null>(
    null,
);

export function TenantChatUnreadProvider({
    children,
    initialUnread = 0,
}: {
    children: ReactNode;
    initialUnread?: number;
}) {
    const [unreadTotal, setUnreadTotal] = useState(initialUnread);
    const [activeConversationId, setActiveConversationId] = useState<string | null>(
        null,
    );

    const setUnread = useCallback((n: number) => {
        setUnreadTotal(Math.max(0, Math.floor(n)));
    }, []);

    const value = useMemo(
        () => ({
            unreadTotal,
            setUnreadTotal: setUnread,
            activeConversationId,
            setActiveConversationId,
        }),
        [unreadTotal, setUnread, activeConversationId],
    );

    return (
        <TenantChatUnreadContext.Provider value={value}>
            {children}
        </TenantChatUnreadContext.Provider>
    );
}

export function useTenantChatUnread(): TenantChatUnreadContextValue {
    const ctx = useContext(TenantChatUnreadContext);
    if (!ctx) {
        return {
            unreadTotal: 0,
            setUnreadTotal: () => undefined,
            activeConversationId: null,
            setActiveConversationId: () => undefined,
        };
    }

    return ctx;
}
