export type AnnouncementBadge = 'nuevo' | 'mejora' | 'importante';

export type AnnouncementEntry = {
    id: string;
    title: string;
    badge: AnnouncementBadge;
    bullet_1: string;
    bullet_2: string | null;
    bullet_3: string | null;
    guide_title: string | null;
    guide_body: string | null;
    guide_tip_1: string | null;
    guide_tip_2: string | null;
    guide_tip_3: string | null;
    is_active: boolean;
    published_at: string | null;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
    created_by?: {
        id: string;
        name: string;
    } | null;
};

export type AnnouncementStatusFilter = 'todos' | 'activo' | 'inactivo' | 'programado';

export type AnnouncementFilters = {
    search: string;
    status: AnnouncementStatusFilter;
    per_page: number;
};

export type TenantAnnouncement = {
    id: string;
    badge: AnnouncementBadge;
    title: string;
    bullets: string[];
    guide_title: string | null;
    guide_body: string | null;
    guide_tips: string[];
    expires_at?: string | null;
};
