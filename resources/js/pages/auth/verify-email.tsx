import { Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth/auth-status';
import VerifyEmailForm from '@/components/auth/verify-email-form';

type Props = {
    status?: string;
};

export default function VerifyEmail({ status }: Props) {
    return (
        <>
            <Head title="Verifica tu correo" />

            {status === 'verification-link-sent' && (
                <AuthStatus variant="success">
                    Te enviamos un nuevo enlace de verificación. Revisa tu
                    bandeja de entrada (y la carpeta de spam, por si acaso).
                </AuthStatus>
            )}

            <VerifyEmailForm />
        </>
    );
}

VerifyEmail.layout = {
    title: 'verifica tu correo.',
    description:
        'Haz click en el enlace que te enviamos para activar tu cuenta y empezar a usar VetSaaS.',
};
