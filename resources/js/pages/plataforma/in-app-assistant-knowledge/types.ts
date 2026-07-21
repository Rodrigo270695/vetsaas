export type KnowledgeScope = 'clinic' | 'platform' | 'both';
export type KnowledgeSection =
    | 'module'
    | 'screen'
    | 'workflow'
    | 'role'
    | 'faq';
export type PermissionMode = 'any' | 'all';

export type KnowledgeAction =
    | {
          type: 'navigate';
          label: string;
          url: string;
          required_permissions?: string[];
          permission_mode?: PermissionMode;
          allowed_roles?: string[];
      }
    | {
          type: 'start_tour';
          label: string;
          tour_id: 'citas' | 'pacientes' | 'historias-clinicas';
          required_permissions?: string[];
          permission_mode?: PermissionMode;
          allowed_roles?: string[];
      };

export type KnowledgeEntry = {
    id: number;
    slug: string;
    scope: KnowledgeScope;
    section: KnowledgeSection;
    title: string;
    content: string;
    keywords: string[] | null;
    url_patterns: string[] | null;
    component_patterns: string[] | null;
    required_permissions: string[] | null;
    permission_mode: PermissionMode;
    allowed_roles: string[] | null;
    actions: KnowledgeAction[] | null;
    priority: number;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type KnowledgeFilters = {
    search: string;
    scope: KnowledgeScope | 'all';
    section: KnowledgeSection | 'all';
    status: 'active' | 'inactive' | 'all';
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

export type KnowledgeStats = {
    total: number;
    active: number;
    platform: number;
    clinic: number;
    matches: number;
};
