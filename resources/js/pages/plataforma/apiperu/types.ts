export type ApiPeruField = {
    name: string;
    label: string;
    type: 'text' | 'date' | 'textarea' | string;
    required: boolean;
    placeholder: string | null;
    hint: string | null;
    max_length: number | null;
    pattern: string | null;
};

export type ApiPeruProfile = {
    id: string;
    label: string;
    description: string;
    icon: string;
    primary_field: ApiPeruField | null;
    extra_fields: ApiPeruField[];
    endpoint_keys: string[];
    tab_labels: Record<string, string>;
};

export type ApiPeruMeta = {
    token_configured: boolean;
    base_url: string;
    docs_url: string;
};

export type ApiPeruIndexProps = {
    profiles: ApiPeruProfile[];
    meta: ApiPeruMeta;
};

export type ApiPeruPerfilResultItem = {
    ok: boolean;
    label: string;
    data?: unknown;
    time?: number | null;
    message?: string;
    code?: string | null;
};

export type ApiPeruPerfilPayload = {
    profile: string;
    label: string;
    subject: string | null;
    ok_count: number;
    fail_count: number;
    results: Record<string, ApiPeruPerfilResultItem>;
};
