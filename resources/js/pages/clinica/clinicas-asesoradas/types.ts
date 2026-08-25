export type GeoOption = {
    id: number;
    name: string;
};

export type ClinicaAsesorada = {
    id: string;
    nombre: string;
    ruc: string | null;
    direccion: string | null;
    distrito_id: number | null;
    distrito: string | null;
    provincia: string | null;
    departamento: string | null;
    activo: boolean;
    mascotas_count: number;
    updated_at: string | null;
};

export type ClinicasAsesoradasPageProps = {
    items: {
        data: ClinicaAsesorada[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        path: string;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string;
        per_page: number;
        estado: 'todas' | 'activa' | 'inactiva';
    };
    stats: {
        total: number;
        activas: number;
        inactivas: number;
        coincidencias: number;
    };
    departamentos: readonly GeoOption[];
};
