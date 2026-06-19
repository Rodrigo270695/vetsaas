import { idbGet, idbSet } from './idb';
import type { CajaBootstrapCache } from './types';

const CACHE_KEY = 'bootstrap:caja';

export async function saveCajaBootstrap(data: CajaBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY, data);
}

export async function loadCajaBootstrap(): Promise<CajaBootstrapCache | null> {
    return idbGet<CajaBootstrapCache>(CACHE_KEY);
}

export function searchCachedProductos(
    cache: CajaBootstrapCache,
    query: string,
    limit = 40,
): CajaBootstrapCache['productos'] {
    const q = query.trim().toLowerCase();

    if (q.length < 2) {
        return [];
    }

    return cache.productos
        .filter((p) => {
            const haystack = [p.nombre, p.sku, p.codigo_barras]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(q);
        })
        .slice(0, limit);
}

export function pacientesForPropietario(
    cache: CajaBootstrapCache,
    propietarioId: string,
): CajaBootstrapCache['pacientes'] {
    return cache.pacientes.filter((p) => p.propietario_id === propietarioId);
}
