import { Head } from '@inertiajs/react';
import ResetPasswordForm from '@/components/auth/reset-password-form';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    return (
        <>
            <Head title="Restablecer contraseña" />

            <ResetPasswordForm token={token} email={email} />
        </>
    );
}

ResetPassword.layout = {
    title: 'define una nueva contraseña.',
    description:
        'Por seguridad, elige una contraseña que no hayas usado antes y mantenla a salvo.',
};
