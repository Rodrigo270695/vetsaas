import { Link } from '@inertiajs/react';
import {
    Braces,
    Download,
    ExternalLink,
    FileText,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import caja from '@/routes/caja';

export type DocumentoDownloadRow = {
    id: string;
    numero_completo: string;
    venta_id: string;
    estado?: string;
    receptor_nombre?: string;
    cliente_telefono?: string | null;
    url_pdf_ticket: string | null;
    url_pdf_a4: string | null;
    tiene_xml: boolean;
    tiene_cdr: boolean;
    download_xml_url: string;
    download_cdr_url: string;
    json_url: string;
};

type Props = {
    documento: DocumentoDownloadRow;
};

export function DocumentoDownloadMenu({ documento }: Props) {
    const { t } = useTranslation('facturacion-documentos');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 border-0 bg-transparent text-sky-600 shadow-none hover:bg-sky-500/10 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                    aria-label={t('actions.descargar', { numero: documento.numero_completo })}
                >
                    <Download className="size-4" strokeWidth={2.25} aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {documento.tiene_xml ? (
                    <DropdownMenuItem asChild className="cursor-pointer gap-2">
                        <a href={documento.download_xml_url}>
                            <Download className="size-4" aria-hidden />
                            Descargar XML
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {documento.tiene_cdr ? (
                    <DropdownMenuItem asChild className="cursor-pointer gap-2">
                        <a href={documento.download_cdr_url}>
                            <Download className="size-4" aria-hidden />
                            Descargar CDR
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {(documento.tiene_xml || documento.tiene_cdr) &&
                (documento.url_pdf_ticket || documento.url_pdf_a4) ? (
                    <DropdownMenuSeparator />
                ) : null}
                {documento.url_pdf_ticket ? (
                    <DropdownMenuItem asChild className="cursor-pointer gap-2">
                        <a href={documento.url_pdf_ticket} target="_blank" rel="noreferrer">
                            <FileText className="size-4" aria-hidden />
                            PDF Ticket
                            <ExternalLink className="ml-auto size-3 opacity-50" aria-hidden />
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {documento.url_pdf_a4 ? (
                    <DropdownMenuItem asChild className="cursor-pointer gap-2">
                        <a href={documento.url_pdf_a4} target="_blank" rel="noreferrer">
                            <FileText className="size-4" aria-hidden />
                            PDF A4
                            <ExternalLink className="ml-auto size-3 opacity-50" aria-hidden />
                        </a>
                    </DropdownMenuItem>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild className="cursor-pointer gap-2">
                    <a href={documento.json_url} target="_blank" rel="noreferrer">
                        <Braces className="size-4" aria-hidden />
                        Ver JSON
                        <ExternalLink className="ml-auto size-3 opacity-50" aria-hidden />
                    </a>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild className="cursor-pointer gap-2">
                    <Link href={caja.ventas.show.url(documento.venta_id)}>
                        <ExternalLink className="size-4" aria-hidden />
                        Ver venta
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
