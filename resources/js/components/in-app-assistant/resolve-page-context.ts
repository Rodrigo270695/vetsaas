import type { Page } from '@inertiajs/core';

export type AssistantPageContext = {
    url: string;
    component: string;
    paciente_id?: string;
};

/**
 * Contexto de pantalla para el asistente (URL + paciente abierto si aplica).
 */
export function resolveAssistantPageContext(page: Page): AssistantPageContext {
    const path =
        typeof window !== 'undefined'
            ? `${window.location.pathname}${window.location.search}`
            : page.url;

    let pacienteId: string | undefined;
    const pacienteProp = (page.props as { paciente?: { id?: unknown } }).paciente;
    if (pacienteProp && typeof pacienteProp.id === 'string' && pacienteProp.id !== '') {
        pacienteId = pacienteProp.id;
    }

    if (!pacienteId) {
        const match = path.match(/\/clinica\/pacientes\/([^/?#]+)/i);
        const candidate = match?.[1];
        if (candidate && candidate !== 'nuevo' && candidate !== 'create') {
            pacienteId = candidate;
        }
    }

    return {
        url: path,
        component: page.component,
        ...(pacienteId ? { paciente_id: pacienteId } : {}),
    };
}
