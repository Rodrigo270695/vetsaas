import { HttpErrorPage } from '@/components/errors/http-error-page';

type Props = {
    message?: string | null;
    attempted_path?: string | null;
    is_authenticated?: boolean;
};

export default function NotFound({
    message = null,
    attempted_path = null,
    is_authenticated = false,
}: Props) {
    return (
        <HttpErrorPage
            status={404}
            message={message}
            attempted_path={attempted_path}
            is_authenticated={is_authenticated}
        />
    );
}
