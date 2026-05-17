import { Form } from '@inertiajs/react';
import { LogOut, MailCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

/**
 * Pantalla intermedia que pide al usuario que verifique su correo.
 * Ofrece reenviar el correo de verificación y cerrar sesión.
 */
export default function VerifyEmailForm() {
    const { t } = useTranslation('auth');

    return (
        <div className="flex flex-col">
            <Form {...send.form()} className="flex flex-col">
                {({ processing }) => (
                    <div className="grid gap-4">
                        <Button
                            type="submit"
                            size="lg"
                            className="h-11 w-full text-base font-medium"
                            disabled={processing}
                            data-test="resend-verification-email-button"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <MailCheck className="size-4" strokeWidth={2.25} />
                            )}
                            {processing
                                ? t('common.submit_loading')
                                : t('verify_email.resend')}
                        </Button>
                    </div>
                )}
            </Form>

            <div className="mt-6 text-center text-sm text-muted-foreground">
                <TextLink
                    href={logout()}
                    method="post"
                    as="button"
                    className="inline-flex items-center gap-1.5 transition-colors"
                >
                    <LogOut className="size-3.5" />
                    {t('verify_email.logout')}
                </TextLink>
            </div>
        </div>
    );
}
