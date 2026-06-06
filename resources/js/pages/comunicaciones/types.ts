import type { WhatsAppProps } from './components/whatsapp-connect-card';
import type { Paginated } from '@/types';

export type NotificationRow = {
    id: string;
    tipo: string;
    canal: string;
    destinatario: string;
    destinatario_nombre: string | null;
    cuerpo: string;
    estado: string;
    enviar_at: string | null;
    intentos: number;
    max_intentos: number;
    error_mensaje: string | null;
    proveedor_msg_id: string | null;
    ultimo_intento_at: string | null;
    created_at: string | null;
};

export type NotificationFilters = {
    search: string;
    per_page: number;
    estado: string;
    tipo: string | null;
};

export type ColaPageProps = {
    items: Paginated<NotificationRow>;
    filters: NotificationFilters;
    stats: Record<string, number>;
    estado_options: string[];
    tipo_options: string[];
    whatsapp: WhatsAppProps;
};

export type HistoricoPageProps = Omit<ColaPageProps, 'whatsapp'>;
