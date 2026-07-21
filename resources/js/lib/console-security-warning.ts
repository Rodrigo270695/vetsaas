let shown = false;

/**
 * Aviso disuasorio contra ataques de ingeniería social (Self-XSS).
 * No reemplaza las validaciones y permisos del servidor.
 */
export function showConsoleSecurityWarning(): void {
    if (shown) {
        return;
    }

    shown = true;

    console.log(
        '%c⚠ ¡ALTO!',
        'color:#dc2626;font-size:52px;font-weight:900;line-height:1.2;text-shadow:1px 1px 0 #7f1d1d;',
    );
    console.log(
        '%c¿Estás seguro de lo que estás haciendo?',
        'color:#111827;font-size:20px;font-weight:800;',
    );
    console.log(
        '%c🔒 Esta consola es una herramienta para desarrolladores. No pegues código que alguien te haya enviado ni compartas aquí contraseñas, tokens o datos personales.',
        'color:#b45309;font-size:14px;font-weight:600;line-height:1.6;',
    );
    console.log(
        '%c🚨 Pegar código desconocido podría permitir que otra persona robe tu cuenta o acceda a la información de tu clínica.',
        'color:#dc2626;font-size:14px;font-weight:700;line-height:1.6;',
    );
    console.log(
        '%cSi alguien de “soporte” te pidió abrir esta pantalla, cierra DevTools y comunícate por los canales oficiales de VetSaaS.',
        'color:#047857;font-size:13px;font-weight:600;line-height:1.6;',
    );
}
