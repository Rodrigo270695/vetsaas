import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthStatus from '@/components/auth/auth-status';
import LoginFlipContent from '@/components/auth/login-flip-content';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation('auth');

    return (
        <>
            <Head title={t('login.submit')} />

            {status && <AuthStatus variant="success">{status}</AuthStatus>}

            <LoginFlipContent canResetPassword={canResetPassword} />
        </>
    );
}

Login.layout = {
    title: 'tu clínica te espera.',
    description:
        'Ingresa con la cuenta de tu clínica para gestionar agenda, historia clínica y caja.',
};
