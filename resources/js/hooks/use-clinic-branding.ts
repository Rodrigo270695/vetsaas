import { usePage } from '@inertiajs/react';
import type { ClinicBranding } from '@/types/clinic-branding';

/**
 * Logo de clínica desde props compartidas (no se pisa por props de página).
 */
export function useClinicBranding(): ClinicBranding | null {
    return (usePage().props.clinic_branding as ClinicBranding | null) ?? null;
}
