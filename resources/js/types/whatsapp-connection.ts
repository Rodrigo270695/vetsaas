export type WhatsAppConnectionShared = {
    scope: 'tenant' | 'platform';
    disconnected: boolean;
    session_id: string | null;
    status: string | null;
    last_synced_at: string | null;
    manage_url: string | null;
};
