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

export type ApiPeruEndpoint = {
    key: string;
    label: string;
    description: string;
    path: string;
    docs_url: string | null;
    fields: ApiPeruField[];
};

export type ApiPeruGroup = {
    id: string;
    label: string;
    description: string;
    endpoints: ApiPeruEndpoint[];
};

export type ApiPeruMeta = {
    token_configured: boolean;
    base_url: string;
    docs_url: string;
};

export type ApiPeruIndexProps = {
    groups: ApiPeruGroup[];
    meta: ApiPeruMeta;
};

export type ApiPeruConsultaSuccess = {
    success: true;
    data: {
        success: boolean;
        data: unknown;
        time?: number | null;
        raw?: unknown;
    };
};

export type ApiPeruConsultaError = {
    success: false;
    message?: string;
    code?: string;
};
