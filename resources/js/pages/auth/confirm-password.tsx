import { Head } from '@inertiajs/react';
import ConfirmPasswordForm from '@/components/auth/confirm-password-form';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contraseña" />

            <ConfirmPasswordForm />
        </>
    );
}

ConfirmPassword.layout = {
    title: 'área segura.',
    description:
        'Estás por entrar a una zona sensible. Confirma tu contraseña para continuar.',
};
