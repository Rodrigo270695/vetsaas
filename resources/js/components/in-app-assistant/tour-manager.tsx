import { router, usePage } from '@inertiajs/react';
import { driver } from 'driver.js';
import type { Driver } from 'driver.js';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    isTourId,
    tourDefinitions,
} from '@/components/in-app-assistant/tour-definitions';
import type { TourId } from '@/components/in-app-assistant/tour-definitions';
import { usePermission } from '@/hooks/use-permission';

const STORAGE_KEY = 'vetsaas.in-app-assistant.active-tour';
const START_EVENT = 'vetsaas:start-tour';
const SELECTOR_RETRIES = 10;
const SELECTOR_RETRY_MS = 100;

type PersistedTourState = {
    tourId: TourId;
    stepIndex: number;
};

function readPersistedState(): PersistedTourState | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const parsed = JSON.parse(
            sessionStorage.getItem(STORAGE_KEY) ?? 'null',
        ) as {
            tourId?: unknown;
            stepIndex?: unknown;
        } | null;

        if (
            parsed === null ||
            !isTourId(parsed.tourId) ||
            typeof parsed.stepIndex !== 'number' ||
            !Number.isInteger(parsed.stepIndex) ||
            parsed.stepIndex < 0
        ) {
            return null;
        }

        return { tourId: parsed.tourId, stepIndex: parsed.stepIndex };
    } catch {
        return null;
    }
}

function persistState(state: PersistedTourState | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        if (state === null) {
            sessionStorage.removeItem(STORAGE_KEY);
        } else {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        }
    } catch {
        // El tour sigue funcionando aunque sessionStorage no esté disponible.
    }
}

function normalizedPath(url: string): string {
    const path = url.split(/[?#]/, 1)[0].replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

export function startInAppTour(tourId: TourId): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(START_EVENT, { detail: { tourId } }));
}

export function TourManager() {
    const { t } = useTranslation('in-app-assistant');
    const page = usePage();
    const { can } = usePermission();
    const [active, setActive] = useState<PersistedTourState | null>(() =>
        readPersistedState(),
    );
    const driverRef = useRef<Driver | null>(null);
    const timeoutRef = useRef<number | null>(null);
    const navigatingToRef = useRef<string | null>(null);

    const destroyDriver = useCallback(() => {
        driverRef.current?.destroy();
        driverRef.current = null;
    }, []);

    const stopTour = useCallback(() => {
        if (timeoutRef.current !== null) {
            window.clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }

        navigatingToRef.current = null;
        destroyDriver();
        persistState(null);
        setActive(null);
    }, [destroyDriver]);

    const moveTo = useCallback(
        (tourId: TourId, stepIndex: number) => {
            destroyDriver();
            const next = { tourId, stepIndex };
            persistState(next);
            setActive(next);
        },
        [destroyDriver],
    );

    useEffect(() => {
        const onStart = (event: Event) => {
            const tourId = (event as CustomEvent<{ tourId?: unknown }>).detail
                ?.tourId;

            if (!isTourId(tourId)) {
                return;
            }

            const definition = tourDefinitions[tourId];

            if (!can(definition.requiredPermission)) {
                stopTour();

                return;
            }

            moveTo(tourId, 0);
        };

        window.addEventListener(START_EVENT, onStart);

        return () => window.removeEventListener(START_EVENT, onStart);
    }, [can, moveTo, stopTour]);

    useEffect(() => {
        if (active === null) {
            return;
        }

        const schedule = (callback: () => void) => {
            timeoutRef.current = window.setTimeout(callback, 0);

            return () => {
                if (timeoutRef.current !== null) {
                    window.clearTimeout(timeoutRef.current);
                    timeoutRef.current = null;
                }
            };
        };
        const definition = tourDefinitions[active.tourId];

        if (!can(definition.requiredPermission)) {
            return schedule(stopTour);
        }

        let stepIndex = active.stepIndex;

        while (stepIndex < definition.steps.length) {
            const permission = definition.steps[stepIndex].permission;

            if (permission === undefined || can(permission)) {
                break;
            }

            stepIndex += 1;
        }

        if (stepIndex >= definition.steps.length) {
            return schedule(stopTour);
        }

        if (stepIndex !== active.stepIndex) {
            return schedule(() => moveTo(active.tourId, stepIndex));
        }

        const step = definition.steps[stepIndex];
        const targetPath = normalizedPath(step.route);
        const currentPath = normalizedPath(page.url);

        if (currentPath !== targetPath) {
            if (navigatingToRef.current !== targetPath) {
                navigatingToRef.current = targetPath;
                persistState(active);
                router.visit(step.route, {
                    onError: () => stopTour(),
                    onFinish: () => {
                        navigatingToRef.current = null;
                    },
                });
            }

            return;
        }

        navigatingToRef.current = null;
        let cancelled = false;
        let attempts = 0;

        const showStep = () => {
            if (cancelled) {
                return;
            }

            const element = document.querySelector(step.selector);

            if (element === null) {
                attempts += 1;

                if (attempts < SELECTOR_RETRIES) {
                    timeoutRef.current = window.setTimeout(
                        showStep,
                        SELECTOR_RETRY_MS,
                    );

                    return;
                }

                moveTo(active.tourId, stepIndex + 1);

                return;
            }

            const isLastAuthorizedStep = definition.steps
                .slice(stepIndex + 1)
                .every(
                    (candidate) =>
                        candidate.permission !== undefined &&
                        !can(candidate.permission),
                );

            const instance = driver({
                animate: true,
                allowClose: true,
                showProgress: true,
                stagePadding: 8,
                stageRadius: 10,
                nextBtnText: t('tour.next'),
                prevBtnText: t('tour.previous'),
                doneBtnText: isLastAuthorizedStep
                    ? t('tour.finish')
                    : t('tour.next'),
                progressText: t('tour.progress'),
                onNextClick: () => moveTo(active.tourId, stepIndex + 1),
                onPrevClick: () =>
                    moveTo(active.tourId, Math.max(0, stepIndex - 1)),
                onCloseClick: stopTour,
                steps: [
                    {
                        element: step.selector,
                        popover: {
                            title: t(step.titleKey),
                            description: t(step.descriptionKey),
                            side: 'bottom',
                            align: 'start',
                            showButtons:
                                stepIndex === 0
                                    ? ['next', 'close']
                                    : ['previous', 'next', 'close'],
                        },
                    },
                ],
            });

            driverRef.current = instance;
            instance.drive();
        };

        timeoutRef.current = window.setTimeout(showStep, 0);

        return () => {
            cancelled = true;

            if (timeoutRef.current !== null) {
                window.clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }

            destroyDriver();
        };
    }, [active, can, destroyDriver, moveTo, page.url, stopTour, t]);

    useEffect(
        () => () => {
            if (timeoutRef.current !== null) {
                window.clearTimeout(timeoutRef.current);
            }

            destroyDriver();
        },
        [destroyDriver],
    );

    return null;
}
