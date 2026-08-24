import type { BaseFilters } from '@/hooks/use-data-table-page';

export type ServicioAgendaTipo = 'grooming' | 'hotel';

export type ServicioAgendaEvento = {
    id: string;
    tipo: ServicioAgendaTipo;
    inicio_at: string;
    fin_at?: string | null;
    duracion_minutos?: number | null;
    estado: string;
    titulo: string;
    subtitulo?: string | null;
    paciente?: {
        id: string;
        nombre: string;
        propietario?: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        } | null;
    } | null;
    responsable?: { id: string; name: string } | null;
    sede?: { id: string; nombre: string; codigo: string } | null;
};

export type ServicioAgendaFilters = BaseFilters & {
    mes: string;
};

export type ServicioAgendaCapabilities = {
    grooming: boolean;
    hotel: boolean;
    grooming_create: boolean;
    hotel_create: boolean;
};

export type ServicioAgendaFormPrefill = {
    fecha?: string;
    hora?: string;
};
