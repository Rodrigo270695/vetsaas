export type KnowledgeSection =
    | 'plan'
    | 'modulo'
    | 'faq'
    | 'objecion'
    | 'novedad'
    | 'general';

export type KnowledgeSectionFilter = KnowledgeSection | 'todos';

export type KnowledgeEntry = {
    id: number;
    product: string;
    section: KnowledgeSection;
    slug: string;
    title: string;
    content: string;
    meta: Record<string, unknown> | null;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type KnowledgeFilters = {
    search: string;
    section: KnowledgeSectionFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

export type KnowledgeStats = {
    total: number;
    planes: number;
    modulos: number;
    faqs: number;
    objeciones: number;
    novedades: number;
    coincidencias: number;
};
