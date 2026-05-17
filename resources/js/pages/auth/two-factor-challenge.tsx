import { Head } from '@inertiajs/react';
import TwoFactorForm from '@/components/auth/two-factor-form';

export default function TwoFactorChallenge() {
    return (
        <>
            <Head title="Verificación en dos pasos" />

            <TwoFactorForm />
        </>
    );
}
