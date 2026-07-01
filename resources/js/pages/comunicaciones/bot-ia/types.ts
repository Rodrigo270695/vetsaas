export type KnowledgeSection =
    | 'faq'
    | 'horario'
    | 'politica'
    | 'servicio'
    | 'contacto'
    | 'general';

export type KnowledgeSectionFilter = KnowledgeSection | 'todos';

export type KnowledgeEntry = {
    id: number;
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
    faqs: number;
    horarios: number;
    politicas: number;
    servicios: number;
    contacto: number;
    general: number;
};

export type ConversationMessage = {
    role: 'user' | 'assistant';
    content: string;
};

export type ConversationEntry = {
    id: string;
    phone: string;
    client_name: string | null;
    bot_active: boolean;
    bot_paused_manually: boolean;
    last_message_at: string | null;
    last_message_preview: string | null;
    turn_count: number;
    messages: ConversationMessage[];
};

export type ConversationEstadoFilter = 'todos' | 'activo' | 'pausado';

export type ConversationFilters = {
    chat_search: string;
    chat_estado: ConversationEstadoFilter;
    chat_per_page: number;
};

export type ConversationStats = {
    total: number;
    activos: number;
    pausados: number;
};

export type AssistantSettings = {
    respuestas_activas: boolean;
};

export type BotIaTab = 'chats' | 'conocimiento';
