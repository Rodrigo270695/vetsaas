import { idbGet, idbSet } from './idb';
import type {
    CajaBootstrapCache,
    ClinicaBootstrapCache,
    ConfiguracionBootstrapCache,
    InventarioBootstrapCache,
    ServiciosBootstrapCache,
} from './types';

const CACHE_KEY_CAJA = 'bootstrap:caja';
const CACHE_KEY_CLINICA = 'bootstrap:clinica';
const CACHE_KEY_INVENTARIO = 'bootstrap:inventario';
const CACHE_KEY_SERVICIOS = 'bootstrap:servicios';
const CACHE_KEY_CONFIGURACION = 'bootstrap:configuracion';

export async function saveCajaBootstrap(data: CajaBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY_CAJA, data);
}

export async function loadCajaBootstrap(): Promise<CajaBootstrapCache | null> {
    return idbGet<CajaBootstrapCache>(CACHE_KEY_CAJA);
}

export async function saveClinicaBootstrap(data: ClinicaBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY_CLINICA, data);
}

export async function loadClinicaBootstrap(): Promise<ClinicaBootstrapCache | null> {
    return idbGet<ClinicaBootstrapCache>(CACHE_KEY_CLINICA);
}

export async function saveInventarioBootstrap(data: InventarioBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY_INVENTARIO, data);
}

export async function loadInventarioBootstrap(): Promise<InventarioBootstrapCache | null> {
    return idbGet<InventarioBootstrapCache>(CACHE_KEY_INVENTARIO);
}

export async function saveServiciosBootstrap(data: ServiciosBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY_SERVICIOS, data);
}

export async function loadServiciosBootstrap(): Promise<ServiciosBootstrapCache | null> {
    return idbGet<ServiciosBootstrapCache>(CACHE_KEY_SERVICIOS);
}

export async function saveConfiguracionBootstrap(data: ConfiguracionBootstrapCache): Promise<void> {
    await idbSet(CACHE_KEY_CONFIGURACION, data);
}

export async function loadConfiguracionBootstrap(): Promise<ConfiguracionBootstrapCache | null> {
    return idbGet<ConfiguracionBootstrapCache>(CACHE_KEY_CONFIGURACION);
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

export function searchCachedPropietarios(
    cache: ClinicaBootstrapCache,
    query: string,
    limit = 40,
): ClinicaBootstrapCache['propietarios'] {
    const q = query.trim().toLowerCase();

    if (q.length < 2) {
        return [];
    }

    return cache.propietarios
        .filter((p) => {
            const haystack = [p.label, p.nombres, p.apellidos, p.razon_social, p.numero_documento]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(q);
        })
        .slice(0, limit);
}

export function searchCachedPacientes(
    cache: ClinicaBootstrapCache,
    query: string,
    limit = 40,
): ClinicaBootstrapCache['pacientes'] {
    const q = query.trim().toLowerCase();

    if (q.length < 2) {
        return [];
    }

    return cache.pacientes
        .filter((p) => {
            const prop = p.propietario;
            const propLabel = prop
                ? [prop.razon_social, prop.nombres, prop.apellidos].filter(Boolean).join(' ')
                : '';
            const haystack = [p.nombre, p.especie, p.raza, propLabel].filter(Boolean).join(' ').toLowerCase();

            return haystack.includes(q);
        })
        .slice(0, limit);
}

export function searchCachedMedicamentos(
    cache: ClinicaBootstrapCache,
    query: string,
    limit = 25,
): ClinicaBootstrapCache['productos_medicamento'] {
    const q = query.trim().toLowerCase();

    if (q.length < 1) {
        return cache.productos_medicamento.slice(0, limit);
    }

    return cache.productos_medicamento
        .filter((p) => {
            const haystack = [p.nombre, p.sku].filter(Boolean).join(' ').toLowerCase();

            return haystack.includes(q);
        })
        .slice(0, limit);
}
