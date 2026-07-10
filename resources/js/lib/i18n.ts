import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import alertasStockEn from '@/lang/en/alertas-stock.json';
import auditoriaLogsEn from '@/lang/en/auditoria-logs.json';
import facturacionDocumentosEn from '@/lang/en/facturacion-documentos.json';
import authEn from '@/lang/en/auth.json';
import ayudaEn from '@/lang/en/ayuda.json';
import cajaEn from '@/lang/en/caja.json';
import categoriasInventarioEn from '@/lang/en/categorias-inventario.json';
import cirugiaEn from '@/lang/en/cirugia.json';
import hospitalizacionEn from '@/lang/en/hospitalizacion.json';
import groomingEn from '@/lang/en/grooming.json';
import hotelEn from '@/lang/en/hotel.json';
import citasEn from '@/lang/en/citas.json';
import cobrosEn from '@/lang/en/cobros.json';
import configSuscripcionEn from '@/lang/en/config-suscripcion.json';
import comunicacionesEn from '@/lang/en/comunicaciones.json';
import plataformaAuditoriaSoporteEn from '@/lang/en/plataforma-auditoria-soporte.json';
import dashboardEn from '@/lang/en/dashboard.json';
import onboardingEn from '@/lang/en/onboarding.json';
import descuentosPromocionesEn from '@/lang/en/descuentos-promociones.json';
import commonEn from '@/lang/en/common.json';
import comprasInventarioEn from '@/lang/en/compras-inventario.json';
import consultaCargosEn from '@/lang/en/consulta-cargos.json';
import generalEn from '@/lang/en/general.json';
import historiasClinicasEn from '@/lang/en/historias-clinicas.json';
import laboratorioEn from '@/lang/en/laboratorio.json';
import movimientosInventarioEn from '@/lang/en/movimientos-inventario.json';
import offlineEn from '@/lang/en/offline.json';
import navEn from '@/lang/en/nav.json';
import pacientesEn from '@/lang/en/pacientes.json';
import planesEn from '@/lang/en/planes.json';
import platformEn from '@/lang/en/platform.json';
import productosInventarioEn from '@/lang/en/productos-inventario.json';
import propietariosEn from '@/lang/en/propietarios.json';
import proveedoresInventarioEn from '@/lang/en/proveedores-inventario.json';
import recetasEn from '@/lang/en/recetas.json';
import rolesEn from '@/lang/en/roles.json';
import settingsEn from '@/lang/en/settings.json';
import sedesEn from '@/lang/en/sedes.json';
import tarifasServiciosEn from '@/lang/en/tarifas-servicios.json';
import stockInventarioEn from '@/lang/en/stock-inventario.json';
import avisosRenovacionEn from '@/lang/en/avisos-renovacion.json';
import botIaEn from '@/lang/en/bot-ia.json';
import suscripcionesEn from '@/lang/en/suscripciones.json';
import subscriptionExpiryEn from '@/lang/en/subscription-expiry.json';
import tenantsEn from '@/lang/en/tenants.json';
import usuariosEn from '@/lang/en/usuarios.json';
import vacunacionesEn from '@/lang/en/vacunaciones.json';
import botIaAnnouncementsEn from '@/lang/en/bot-ia-announcements.json';
import salesbotKnowledgeEn from '@/lang/en/salesbot-knowledge.json';
import alertasStockEs from '@/lang/es/alertas-stock.json';
import auditoriaLogsEs from '@/lang/es/auditoria-logs.json';
import facturacionDocumentosEs from '@/lang/es/facturacion-documentos.json';
import authEs from '@/lang/es/auth.json';
import ayudaEs from '@/lang/es/ayuda.json';
import cajaEs from '@/lang/es/caja.json';
import categoriasInventarioEs from '@/lang/es/categorias-inventario.json';
import cirugiaEs from '@/lang/es/cirugia.json';
import hospitalizacionEs from '@/lang/es/hospitalizacion.json';
import groomingEs from '@/lang/es/grooming.json';
import hotelEs from '@/lang/es/hotel.json';
import citasEs from '@/lang/es/citas.json';
import cobrosEs from '@/lang/es/cobros.json';
import configSuscripcionEs from '@/lang/es/config-suscripcion.json';
import comunicacionesEs from '@/lang/es/comunicaciones.json';
import plataformaAuditoriaSoporteEs from '@/lang/es/plataforma-auditoria-soporte.json';
import dashboardEs from '@/lang/es/dashboard.json';
import onboardingEs from '@/lang/es/onboarding.json';
import descuentosPromocionesEs from '@/lang/es/descuentos-promociones.json';
import commonEs from '@/lang/es/common.json';
import comprasInventarioEs from '@/lang/es/compras-inventario.json';
import consultaCargosEs from '@/lang/es/consulta-cargos.json';
import generalEs from '@/lang/es/general.json';
import historiasClinicasEs from '@/lang/es/historias-clinicas.json';
import laboratorioEs from '@/lang/es/laboratorio.json';
import movimientosInventarioEs from '@/lang/es/movimientos-inventario.json';
import offlineEs from '@/lang/es/offline.json';
import navEs from '@/lang/es/nav.json';
import pacientesEs from '@/lang/es/pacientes.json';
import planesEs from '@/lang/es/planes.json';
import platformEs from '@/lang/es/platform.json';
import productosInventarioEs from '@/lang/es/productos-inventario.json';
import propietariosEs from '@/lang/es/propietarios.json';
import proveedoresInventarioEs from '@/lang/es/proveedores-inventario.json';
import recetasEs from '@/lang/es/recetas.json';
import rolesEs from '@/lang/es/roles.json';
import settingsEs from '@/lang/es/settings.json';
import sedesEs from '@/lang/es/sedes.json';
import tarifasServiciosEs from '@/lang/es/tarifas-servicios.json';
import stockInventarioEs from '@/lang/es/stock-inventario.json';
import avisosRenovacionEs from '@/lang/es/avisos-renovacion.json';
import botIaEs from '@/lang/es/bot-ia.json';
import suscripcionesEs from '@/lang/es/suscripciones.json';
import subscriptionExpiryEs from '@/lang/es/subscription-expiry.json';
import tenantsEs from '@/lang/es/tenants.json';
import usuariosEs from '@/lang/es/usuarios.json';
import vacunacionesEs from '@/lang/es/vacunaciones.json';
import botIaAnnouncementsEs from '@/lang/es/bot-ia-announcements.json';
import salesbotKnowledgeEs from '@/lang/es/salesbot-knowledge.json';

/**
 * Idiomas disponibles en la aplicación. Mantén alineado con los archivos
 * en `resources/js/lang/<locale>/*.json` y con los selectores de UI.
 */
export const SUPPORTED_LOCALES = ['es', 'en'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

/** Llave bajo la que se guarda el idioma elegido en localStorage. */
export const LOCALE_STORAGE_KEY = 'vetsaas.locale';

/**
 * Estructura de los recursos de traducción.
 *
 * Cada idioma agrupa sus textos por "namespace" (ámbito funcional).
 * Esto permite hacer code-splitting más adelante si crece el bundle.
 */
const resources = {
    es: {
        common: commonEs,
        'categorias-inventario': categoriasInventarioEs,
        'productos-inventario': productosInventarioEs,
        nav: navEs,
        pacientes: pacientesEs,
        propietarios: propietariosEs,
        sedes: sedesEs,
        'stock-inventario': stockInventarioEs,
        roles: rolesEs,
        settings: settingsEs,
        usuarios: usuariosEs,
        tenants: tenantsEs,
        planes: planesEs,
        suscripciones: suscripcionesEs,
        'avisos-renovacion': avisosRenovacionEs,
        cobros: cobrosEs,
        'subscription-expiry': subscriptionExpiryEs,
        'config-suscripcion': configSuscripcionEs,
        comunicaciones: comunicacionesEs,
        'bot-ia': botIaEs,
        'plataforma-auditoria-soporte': plataformaAuditoriaSoporteEs,
        dashboard: dashboardEs,
        onboarding: onboardingEs,
        auth: authEs,
        ayuda: ayudaEs,
        general: generalEs,
        platform: platformEs,
        'historias-clinicas': historiasClinicasEs,
        'movimientos-inventario': movimientosInventarioEs,
        offline: offlineEs,
        'alertas-stock': alertasStockEs,
        'auditoria-logs': auditoriaLogsEs,
        'proveedores-inventario': proveedoresInventarioEs,
        'compras-inventario': comprasInventarioEs,
        vacunaciones: vacunacionesEs,
        citas: citasEs,
        cirugia: cirugiaEs,
        hospitalizacion: hospitalizacionEs,
        grooming: groomingEs,
        hotel: hotelEs,
        'consulta-cargos': consultaCargosEs,
        recetas: recetasEs,
        laboratorio: laboratorioEs,
        caja: cajaEs,
        'facturacion-documentos': facturacionDocumentosEs,
        'tarifas-servicios': tarifasServiciosEs,
        'descuentos-promociones': descuentosPromocionesEs,
        'bot-ia-announcements': botIaAnnouncementsEs,
        'salesbot-knowledge': salesbotKnowledgeEs,
    },
    en: {
        common: commonEn,
        'categorias-inventario': categoriasInventarioEn,
        'productos-inventario': productosInventarioEn,
        nav: navEn,
        pacientes: pacientesEn,
        propietarios: propietariosEn,
        sedes: sedesEn,
        'stock-inventario': stockInventarioEn,
        roles: rolesEn,
        settings: settingsEn,
        usuarios: usuariosEn,
        tenants: tenantsEn,
        planes: planesEn,
        suscripciones: suscripcionesEn,
        'avisos-renovacion': avisosRenovacionEn,
        cobros: cobrosEn,
        'subscription-expiry': subscriptionExpiryEn,
        'config-suscripcion': configSuscripcionEn,
        comunicaciones: comunicacionesEn,
        'bot-ia': botIaEn,
        'plataforma-auditoria-soporte': plataformaAuditoriaSoporteEn,
        dashboard: dashboardEn,
        onboarding: onboardingEn,
        auth: authEn,
        ayuda: ayudaEn,
        general: generalEn,
        platform: platformEn,
        'historias-clinicas': historiasClinicasEn,
        'movimientos-inventario': movimientosInventarioEn,
        offline: offlineEn,
        'alertas-stock': alertasStockEn,
        'auditoria-logs': auditoriaLogsEn,
        'proveedores-inventario': proveedoresInventarioEn,
        'compras-inventario': comprasInventarioEn,
        vacunaciones: vacunacionesEn,
        citas: citasEn,
        cirugia: cirugiaEn,
        hospitalizacion: hospitalizacionEn,
        grooming: groomingEn,
        hotel: hotelEn,
        'consulta-cargos': consultaCargosEn,
        recetas: recetasEn,
        laboratorio: laboratorioEn,
        caja: cajaEn,
        'facturacion-documentos': facturacionDocumentosEn,
        'tarifas-servicios': tarifasServiciosEn,
        'descuentos-promociones': descuentosPromocionesEn,
        'bot-ia-announcements': botIaAnnouncementsEn,
        'salesbot-knowledge': salesbotKnowledgeEn,
    },
} as const;

void i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources,
        fallbackLng: DEFAULT_LOCALE,
        supportedLngs: SUPPORTED_LOCALES as unknown as string[],
        defaultNS: 'common',
        ns: [
            'common',
            'categorias-inventario',
            'productos-inventario',
            'stock-inventario',
            'nav',
            'pacientes',
            'propietarios',
            'sedes',
            'roles',
            'settings',
            'usuarios',
            'tenants',
            'planes',
            'suscripciones',
            'avisos-renovacion',
            'cobros',
            'subscription-expiry',
            'config-suscripcion',
            'comunicaciones',
            'bot-ia',
            'dashboard',
            'onboarding',
            'auth',
            'ayuda',
            'general',
            'platform',
            'historias-clinicas',
            'movimientos-inventario',
            'offline',
            'alertas-stock',
            'proveedores-inventario',
            'compras-inventario',
            'vacunaciones',
            'citas',
            'cirugia',
            'hospitalizacion',
            'grooming',
            'hotel',
            'consulta-cargos',
            'recetas',
            'laboratorio',
            'caja',
            'tarifas-servicios',
            'descuentos-promociones',
            'salesbot-knowledge',
            'bot-ia-announcements',
        ],
        interpolation: {
            // React ya escapa por defecto. Evita doble escaping.
            escapeValue: false,
        },
        detection: {
            order: ['localStorage', 'navigator', 'htmlTag'],
            lookupLocalStorage: LOCALE_STORAGE_KEY,
            caches: ['localStorage'],
        },
        react: {
            useSuspense: false,
        },
    });

/** Aplica el atributo `lang` al `<html>` para SEO + accesibilidad. */
function syncHtmlLang(locale: string): void {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('lang', locale);
    }
}

syncHtmlLang(i18n.language || DEFAULT_LOCALE);
i18n.on('languageChanged', syncHtmlLang);

export default i18n;
