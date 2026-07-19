export type InAppAnnouncementRecord = {
    id: string;
    title: string;
    body: string;
    features: string[];
    is_active: boolean;
    version: number;
    published_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    created_by: { id: string; name: string } | null;
};
