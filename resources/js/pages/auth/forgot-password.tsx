import { Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth/auth-status';
import ForgotPasswordForm from '@/components/auth/forgot-password-form';

type Props = {
    status?: string;
};

export default function ForgotPassword({ status }: Props) {
    return (
        <>
            <Head title="Recuperar contraseña" />

            {status && <AuthStatus variant="success">{status}</AuthStatus>}

            <ForgotPasswordForm />
        </>
    );
}

ForgotPassword.layout = {
    title: '¿olvidaste tu contraseña?',
    description:
        'Ingresa el correo de tu cuenta y te enviaremos un enlace para crear una nueva.',
};
