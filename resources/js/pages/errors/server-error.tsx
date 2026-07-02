import { HttpErrorPage } from '@/components/errors/http-error-page';

type Props = {
    message?: string | null;
    attempted_path?: string | null;
    is_authenticated?: boolean;
    status?: 500 | 503;
};

export default function ServerError({
    message = null,
    attempted_path = null,
    is_authenticated = false,
    status = 500,
}: Props) {
    return (
        <HttpErrorPage
            status={status}
            message={message}
            attempted_path={attempted_path}
            is_authenticated={is_authenticated}
        />
    );
}
