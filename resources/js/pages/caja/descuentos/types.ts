import type { BaseFilters } from '@/hooks/use-data-table-page';

export type Promotion = {
    id: string;
    name: string;
    code: string | null;
    description: string | null;
    discount_type: string;
    value: string;
    scope: string;
    condition_type: string;
    grooming_service_slug: string | null;
    producto_id: string | null;
    auto_apply: boolean;
    is_active: boolean;
    valid_from: string | null;
    valid_until: string | null;
    max_uses: number | null;
    uses_count: number;
    priority: number;
    created_at: string;
    updated_at: string;
};

export type PromotionStats = {
    total: number;
    activas: number;
    inactivas: number;
    coincidencias: number;
};

export type PromotionEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type PromotionFilters = BaseFilters & {
    estado: PromotionEstadoFilter;
};

export type GroomingServiceOption = {
    value: string;
    label: string;
};

export type ProductOption = {
    id: string;
    nombre: string;
    sku: string | null;
};

export type PromotionMeta = {
    discount_types: readonly string[];
    scopes: readonly string[];
    condition_types: readonly string[];
};
