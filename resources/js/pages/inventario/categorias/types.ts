import type { BaseFilters } from '@/hooks/use-data-table-page';

export type CategoriaAuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

export type CategoriaParent = {
    id: string;
    nombre: string;
    slug: string | null;
} | null;

export type CategoriaProducto = {
    id: string;
    parent_id: string | null;
    nombre: string;
    slug: string | null;
    descripcion: string | null;
    orden: number;
    activo: boolean;
    created_at: string;
    updated_at: string;
    created_by_id: string | null;
    updated_by_id: string | null;
    parent: CategoriaParent;
    creado_por: CategoriaAuditUser;
    actualizado_por: CategoriaAuditUser;
};

export type CategoriaParentOption = {
    id: string;
    nombre: string;
};

export type CategoriaStats = {
    total: number;
    activas: number;
    inactivas: number;
    coincidencias: number;
};

export type CategoriaEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type CategoriaFilters = BaseFilters & {
    estado: CategoriaEstadoFilter;
};
