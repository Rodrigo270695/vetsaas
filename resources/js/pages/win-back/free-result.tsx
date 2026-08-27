import { Head } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, Clock, ExternalLink } from 'lucide-react';

type Props = {
    status: string;
    message: string;
    login_url: string | null;
    granted_days: number | null;
};

const STATUS_UI: Record<
    string,
    { title: string; icon: typeof CheckCircle2; tone: string }
> = {
    accepted: {
        title: 'Mes gratis activado',
        icon: CheckCircle2,
        tone: 'text-emerald-600 bg-emerald-50 border-emerald-200',
    },
    already_accepted: {
        title: 'Oferta ya aceptada',
        icon: CheckCircle2,
        tone: 'text-emerald-600 bg-emerald-50 border-emerald-200',
    },
    expired: {
        title: 'Enlace expirado',
        icon: Clock,
        tone: 'text-amber-700 bg-amber-50 border-amber-200',
    },
    invalid: {
        title: 'Enlace no válido',
        icon: CircleAlert,
        tone: 'text-red-600 bg-red-50 border-red-200',
    },
    error: {
        title: 'No se pudo completar',
        icon: CircleAlert,
        tone: 'text-red-600 bg-red-50 border-red-200',
    },
};

export default function FreeWinBackResult({
    status,
    message,
    login_url,
}: Props) {
    const ui = STATUS_UI[status] ?? STATUS_UI.error;
    const Icon = ui.icon;

    return (
        <>
            <Head title={ui.title} />
            <div className="flex min-h-svh items-center justify-center bg-slate-50 px-4 py-10">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div
                        className={`mb-5 inline-flex size-12 items-center justify-center rounded-full border ${ui.tone}`}
                    >
                        <Icon className="size-6" />
                    </div>
                    <h1 className="text-xl font-semibold text-slate-900">
                        {ui.title}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                        {message}
                    </p>

                    {login_url && (
                        <a
                            href={login_url}
                            className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            Entrar a mi clínica
                            <ExternalLink className="size-4" />
                        </a>
                    )}

                    <p className="mt-6 text-center text-xs text-slate-400">
                        VetSaaS · Orvae
                    </p>
                </div>
            </div>
        </>
    );
}
