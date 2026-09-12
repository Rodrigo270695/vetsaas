import { Loader2, Mic, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import FluidOrb from '@/components/ui/fluid-orb';
import { cn } from '@/lib/utils';

export type ConsultaDictationFields = {
    motivo: string | null;
    subjetivo: string | null;
    objetivo: string | null;
    analisis: string | null;
    plan: string | null;
    peso_kg: string | null;
    temperatura_c: string | null;
    fc_lpm: string | null;
    fr_rpm: string | null;
};

type Props = {
    disabled?: boolean;
    onFields: (fields: ConsultaDictationFields, transcript: string) => void;
};

type SpeechRecognitionLike = {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    start: () => void;
    stop: () => void;
    abort: () => void;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: { error?: string }) => void) | null;
    onend: (() => void) | null;
};

type SpeechRecognitionEventLike = {
    resultIndex: number;
    results: ArrayLike<{
        isFinal: boolean;
        0: { transcript: string };
    }>;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function getSpeechRecognitionCtor(): (new () => SpeechRecognitionLike) | null {
    const w = window as Window & {
        SpeechRecognition?: new () => SpeechRecognitionLike;
        webkitSpeechRecognition?: new () => SpeechRecognitionLike;
    };
    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

function isLikelyMobile(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}

export function ConsultaDictationBar({ disabled = false, onFields }: Props) {
    const { t } = useTranslation('historias-clinicas');
    const [listening, setListening] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [liveText, setLiveText] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [mode, setMode] = useState<'speech' | 'media' | null>(null);

    const recognitionRef = useRef<SpeechRecognitionLike | null>(null);
    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const mediaChunksRef = useRef<Blob[]>([]);
    const mediaStreamRef = useRef<MediaStream | null>(null);
    const finalTranscriptRef = useRef('');
    const liveTextRef = useRef('');
    const stopRequestedRef = useRef(false);
    const listeningIntentRef = useRef(false);
    const disposingRef = useRef(false);
    const restartCountRef = useRef(0);
    const processTranscriptRef = useRef<(transcript: string) => Promise<void>>(async () => {});

    const capturedText = () =>
        (finalTranscriptRef.current.trim() || liveTextRef.current.trim()).trim();

    useEffect(() => {
        disposingRef.current = false;
        return () => {
            disposingRef.current = true;
            stopAll(false);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const stopMediaTracks = () => {
        mediaStreamRef.current?.getTracks().forEach((track) => track.stop());
        mediaStreamRef.current = null;
    };

    const stopAll = (processAfter = false) => {
        stopRequestedRef.current = true;
        listeningIntentRef.current = false;
        try {
            recognitionRef.current?.abort();
        } catch {
            // ignore
        }
        recognitionRef.current = null;
        const recorder = mediaRecorderRef.current;
        if (recorder && recorder.state !== 'inactive') {
            try {
                recorder.stop();
            } catch {
                // ignore
            }
        }
        mediaRecorderRef.current = null;
        stopMediaTracks();
        setListening(false);
        if (processAfter) {
            void processTranscriptRef.current(capturedText());
        }
    };

    const processTranscript = async (transcript: string) => {
        const text = transcript.trim();
        if (text.length < 3) {
            setError(t('dictation.empty'));
            return;
        }

        setProcessing(true);
        setError(null);
        try {
            const res = await fetch('/clinica/historias-clinicas/consultas/dictar', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ transcript: text }),
            });
            const body = (await res.json()) as {
                message?: string;
                transcript?: string;
                fields?: ConsultaDictationFields;
            };
            if (!res.ok) {
                throw new Error(body.message || t('dictation.error'));
            }
            if (!body.fields) {
                throw new Error(t('dictation.error'));
            }
            onFields(body.fields, body.transcript ?? text);
            setLiveText(body.transcript ?? text);
            liveTextRef.current = body.transcript ?? text;
            finalTranscriptRef.current = '';
        } catch (e) {
            setError(e instanceof Error ? e.message : t('dictation.error'));
        } finally {
            setProcessing(false);
        }
    };
    processTranscriptRef.current = processTranscript;

    const processAudio = async (blob: Blob) => {
        if (blob.size < 500) {
            setError(t('dictation.empty'));
            return;
        }

        setProcessing(true);
        setError(null);
        try {
            const form = new FormData();
            form.append('audio', blob, 'dictado.webm');
            const res = await fetch('/clinica/historias-clinicas/consultas/dictar', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: form,
            });
            const body = (await res.json()) as {
                message?: string;
                transcript?: string;
                fields?: ConsultaDictationFields;
            };
            if (!res.ok) {
                throw new Error(body.message || t('dictation.error'));
            }
            if (!body.fields) {
                throw new Error(t('dictation.error'));
            }
            onFields(body.fields, body.transcript ?? '');
            setLiveText(body.transcript ?? '');
            liveTextRef.current = body.transcript ?? '';
            finalTranscriptRef.current = '';
        } catch (e) {
            setError(e instanceof Error ? e.message : t('dictation.error'));
        } finally {
            setProcessing(false);
        }
    };

    const bindSpeechHandlers = (recognition: SpeechRecognitionLike, Ctor: new () => SpeechRecognitionLike) => {
        recognition.onresult = (event) => {
            let interim = '';
            let finalChunk = finalTranscriptRef.current;
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const piece = event.results[i][0]?.transcript ?? '';
                if (event.results[i].isFinal) {
                    finalChunk = `${finalChunk} ${piece}`.trim();
                } else {
                    interim += piece;
                }
            }
            finalTranscriptRef.current = finalChunk;
            const display = `${finalChunk}${interim ? ` ${interim}` : ''}`.trim();
            liveTextRef.current = display;
            setLiveText(display);
        };

        recognition.onerror = (event) => {
            if (event.error === 'aborted') {
                return;
            }
            // En móvil "no-speech" suele cerrar la sesión: no es error fatal.
            if (event.error === 'no-speech') {
                return;
            }
            listeningIntentRef.current = false;
            setError(t('dictation.mic_error'));
            setListening(false);
        };

        recognition.onend = () => {
            recognitionRef.current = null;
            if (disposingRef.current) {
                return;
            }

            // Usuario pulsó detener (o cleanup con process).
            if (stopRequestedRef.current || !listeningIntentRef.current) {
                setListening(false);
                listeningIntentRef.current = false;
                if (!disposingRef.current) {
                    void processTranscript(capturedText());
                }
                return;
            }

            // Chrome móvil corta solo tras un silencio: reintentar escuchar.
            if (restartCountRef.current < 10) {
                restartCountRef.current += 1;
                try {
                    const next = new Ctor();
                    next.lang = 'es-PE';
                    next.continuous = true;
                    next.interimResults = true;
                    bindSpeechHandlers(next, Ctor);
                    recognitionRef.current = next;
                    next.start();
                    return;
                } catch {
                    // caer a procesar
                }
            }

            listeningIntentRef.current = false;
            setListening(false);
            void processTranscript(capturedText());
        };
    };

    const startSpeech = () => {
        const Ctor = getSpeechRecognitionCtor();
        if (!Ctor) {
            return false;
        }

        // En móvil el Web Speech se corta solo y a menudo no marca "final":
        // preferimos grabar audio + Whisper (más fiable para rellenar campos).
        if (isLikelyMobile()) {
            return false;
        }

        const recognition = new Ctor();
        recognition.lang = 'es-PE';
        recognition.continuous = true;
        recognition.interimResults = true;
        finalTranscriptRef.current = '';
        liveTextRef.current = '';
        setLiveText('');
        stopRequestedRef.current = false;
        listeningIntentRef.current = true;
        restartCountRef.current = 0;

        bindSpeechHandlers(recognition, Ctor);
        recognitionRef.current = recognition;
        recognition.start();
        setMode('speech');
        setListening(true);
        setError(null);
        return true;
    };

    const startMedia = async () => {
        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            setError(t('dictation.unsupported'));
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaStreamRef.current = stream;
        mediaChunksRef.current = [];
        const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : MediaRecorder.isTypeSupported('audio/webm')
              ? 'audio/webm'
              : '';
        const recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
        mediaRecorderRef.current = recorder;
        stopRequestedRef.current = false;
        listeningIntentRef.current = true;

        recorder.ondataavailable = (e) => {
            if (e.data.size > 0) {
                mediaChunksRef.current.push(e.data);
            }
        };
        recorder.onstop = () => {
            const blob = new Blob(mediaChunksRef.current, { type: recorder.mimeType || 'audio/webm' });
            mediaChunksRef.current = [];
            stopMediaTracks();
            mediaRecorderRef.current = null;
            setListening(false);
            listeningIntentRef.current = false;
            if (disposingRef.current) {
                return;
            }
            // En media siempre procesamos al parar (manual).
            if (stopRequestedRef.current) {
                void processAudio(blob);
            }
        };

        recorder.start(1000);
        setMode('media');
        setListening(true);
        setLiveText(t('dictation.recording'));
        liveTextRef.current = '';
        setError(null);
    };

    const start = async () => {
        if (disabled || processing || listening) {
            return;
        }
        setError(null);
        if (startSpeech()) {
            return;
        }
        try {
            await startMedia();
        } catch {
            setError(t('dictation.mic_error'));
            stopMediaTracks();
        }
    };

    const stop = () => {
        if (!listening) {
            return;
        }
        stopRequestedRef.current = true;
        listeningIntentRef.current = false;
        if (mode === 'speech' && recognitionRef.current) {
            try {
                recognitionRef.current.stop();
            } catch {
                setListening(false);
                void processTranscript(capturedText());
            }
            return;
        }
        if (mode === 'media' && mediaRecorderRef.current) {
            try {
                mediaRecorderRef.current.stop();
            } catch {
                setListening(false);
            }
        }
    };

    return (
        <div
            className={cn(
                'rounded-xl border px-3 py-2.5',
                listening || processing
                    ? 'border-sky-300/80 bg-sky-50/70 dark:border-sky-800/50 dark:bg-sky-950/30'
                    : 'border-sky-200/80 bg-sky-50/50 dark:border-sky-800/40 dark:bg-sky-950/20',
            )}
        >
            {listening || processing ? (
                <div className="flex flex-col items-center gap-3 py-3">
                    <button
                        type="button"
                        className="relative cursor-pointer rounded-full outline-none focus-visible:ring-2 focus-visible:ring-sky-400/70 focus-visible:ring-offset-2 disabled:cursor-wait"
                        disabled={processing}
                        aria-label={processing ? t('dictation.processing') : t('dictation.stop')}
                        onClick={() => {
                            if (!processing) {
                                stop();
                            }
                        }}
                    >
                        <FluidOrb
                            size={128}
                            color="#1A73F2"
                            className="shadow-[0_8px_32px_rgba(26,115,242,0.35)]"
                        />
                        {processing ? (
                            <span className="absolute inset-0 flex items-center justify-center rounded-full bg-background/20">
                                <Loader2 className="size-7 animate-spin text-white drop-shadow" />
                            </span>
                        ) : null}
                    </button>
                    <p className="text-center text-xs text-muted-foreground">
                        {processing ? t('dictation.processing') : t('dictation.listening')}
                    </p>
                    {(liveText || error) && (
                        <div className="w-full space-y-1">
                            {liveText ? (
                                <p className="line-clamp-4 text-center text-xs leading-relaxed text-foreground/90">
                                    {liveText}
                                </p>
                            ) : null}
                            {error ? (
                                <p className="text-center text-xs text-destructive">{error}</p>
                            ) : null}
                        </div>
                    )}
                    {!processing ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-8 gap-1.5"
                            onClick={stop}
                        >
                            <Square className="size-3.5 fill-current" />
                            {t('dictation.stop')}
                        </Button>
                    ) : null}
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-8 gap-1.5"
                            disabled={disabled}
                            onClick={() => void start()}
                        >
                            <Mic className="size-3.5" />
                            {t('dictation.start')}
                        </Button>
                        <p className="min-w-0 flex-1 text-xs text-muted-foreground">
                            {t('dictation.hint')}
                        </p>
                    </div>
                    {error ? (
                        <p className="mt-2 text-xs text-destructive">{error}</p>
                    ) : null}
                </>
            )}
        </div>
    );
}
