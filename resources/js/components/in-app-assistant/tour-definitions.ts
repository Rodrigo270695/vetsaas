import type { PermissionInput } from '@/hooks/use-permission';

export const TOUR_IDS = ['citas', 'pacientes', 'historias-clinicas'] as const;

export type TourId = (typeof TOUR_IDS)[number];

export type TourStepDefinition = {
    selector: `[data-tour-id="${string}"]`;
    titleKey: string;
    descriptionKey: string;
    route: string;
    permission?: PermissionInput;
};

export type TourDefinition = {
    id: TourId;
    requiredPermission: string;
    steps: readonly TourStepDefinition[];
};

export const tourDefinitions: Record<TourId, TourDefinition> = {
    citas: {
        id: 'citas',
        requiredPermission: 'citas.view',
        steps: [
            {
                selector: '[data-tour-id="citas-header"]',
                titleKey: 'tour.steps.citas.header.title',
                descriptionKey: 'tour.steps.citas.header.description',
                route: '/clinica/citas',
            },
            {
                selector: '[data-tour-id="citas-filters"]',
                titleKey: 'tour.steps.citas.filters.title',
                descriptionKey: 'tour.steps.citas.filters.description',
                route: '/clinica/citas',
            },
            {
                selector: '[data-tour-id="citas-create"]',
                titleKey: 'tour.steps.citas.create.title',
                descriptionKey: 'tour.steps.citas.create.description',
                route: '/clinica/citas',
                permission: 'citas.create',
            },
            {
                selector: '[data-tour-id="citas-view"]',
                titleKey: 'tour.steps.citas.view.title',
                descriptionKey: 'tour.steps.citas.view.description',
                route: '/clinica/citas',
            },
            {
                selector: '[data-tour-id="citas-actions"]',
                titleKey: 'tour.steps.citas.actions.title',
                descriptionKey: 'tour.steps.citas.actions.description',
                route: '/clinica/citas',
                permission: ['citas.update', 'citas.cancel', 'citas.aperturar'],
            },
        ],
    },
    pacientes: {
        id: 'pacientes',
        requiredPermission: 'pacientes.view',
        steps: [
            {
                selector: '[data-tour-id="pacientes-header"]',
                titleKey: 'tour.steps.pacientes.header.title',
                descriptionKey: 'tour.steps.pacientes.header.description',
                route: '/clinica/pacientes',
            },
            {
                selector: '[data-tour-id="pacientes-create"]',
                titleKey: 'tour.steps.pacientes.create.title',
                descriptionKey: 'tour.steps.pacientes.create.description',
                route: '/clinica/pacientes',
                permission: 'pacientes.create',
            },
            {
                selector: '[data-tour-id="pacientes-filters"]',
                titleKey: 'tour.steps.pacientes.filters.title',
                descriptionKey: 'tour.steps.pacientes.filters.description',
                route: '/clinica/pacientes',
            },
            {
                selector: '[data-tour-id="pacientes-list"]',
                titleKey: 'tour.steps.pacientes.list.title',
                descriptionKey: 'tour.steps.pacientes.list.description',
                route: '/clinica/pacientes',
            },
            {
                selector: '[data-tour-id="pacientes-actions"]',
                titleKey: 'tour.steps.pacientes.actions.title',
                descriptionKey: 'tour.steps.pacientes.actions.description',
                route: '/clinica/pacientes',
                permission: [
                    'pacientes.update',
                    'pacientes.delete',
                    'vacunaciones.view',
                ],
            },
        ],
    },
    'historias-clinicas': {
        id: 'historias-clinicas',
        requiredPermission: 'historias-clinicas.view',
        steps: [
            {
                selector: '[data-tour-id="historias-header"]',
                titleKey: 'tour.steps.historias.header.title',
                descriptionKey: 'tour.steps.historias.header.description',
                route: '/clinica/historias-clinicas',
            },
            {
                selector: '[data-tour-id="historias-create"]',
                titleKey: 'tour.steps.historias.create.title',
                descriptionKey: 'tour.steps.historias.create.description',
                route: '/clinica/historias-clinicas',
                permission: 'historias-clinicas.create',
            },
            {
                selector: '[data-tour-id="historias-filters"]',
                titleKey: 'tour.steps.historias.filters.title',
                descriptionKey: 'tour.steps.historias.filters.description',
                route: '/clinica/historias-clinicas',
            },
            {
                selector: '[data-tour-id="historias-list"]',
                titleKey: 'tour.steps.historias.list.title',
                descriptionKey: 'tour.steps.historias.list.description',
                route: '/clinica/historias-clinicas',
            },
            {
                selector: '[data-tour-id="historias-actions"]',
                titleKey: 'tour.steps.historias.actions.title',
                descriptionKey: 'tour.steps.historias.actions.description',
                route: '/clinica/historias-clinicas',
                permission: [
                    'historias-clinicas.update',
                    'historias-clinicas-planes.view',
                    'consulta-cargos.view',
                ],
            },
        ],
    },
};

export function isTourId(value: unknown): value is TourId {
    return (
        typeof value === 'string' &&
        (TOUR_IDS as readonly string[]).includes(value)
    );
}
