const SRC = '/sounds/sala-espera-bell.mp3';

let audio: HTMLAudioElement | null = null;
let primed = false;

function element(): HTMLAudioElement | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (audio === null) {
        audio = new Audio(SRC);
        audio.preload = 'auto';
    }

    return audio;
}

/** Desbloquea el audio tras el primer clic, para que luego suene aunque la ventana esté minimizada. */
export function primeSalaEsperaSound(): void {
    if (primed) {
        return;
    }

    const el = element();
    if (el === null) {
        return;
    }

    el.volume = 0;
    const played = el.play();
    primed = true;

    if (played === undefined) {
        el.pause();
        el.currentTime = 0;
        el.volume = 1;

        return;
    }

    void played
        .then(() => {
            el.pause();
            el.currentTime = 0;
            el.volume = 1;
        })
        .catch(() => {
            primed = false;
            el.volume = 1;
        });
}

export function playSalaEsperaSound(): void {
    const el = element();
    if (el === null) {
        return;
    }

    el.volume = 1;

    try {
        el.currentTime = 0;
    } catch {
        // El archivo todavía no está listo.
    }

    void el.play().catch(() => {
        // El navegador bloquea el audio hasta que haya un clic en la página.
    });
}
