import { ApiPeruConsultaPanel } from './apiperu-consulta-panel';
import type { ApiPeruGroup } from '../types';

type Props = {
    group: ApiPeruGroup;
    consultarUrl: string;
    disabled?: boolean;
};

/**
 * Componente general de tab: renderiza todos los paneles de consulta
 * del grupo (identidad, finanzas, etc.). Reutilizable por categoría.
 */
export function ApiPeruGroupTab({ group, consultarUrl, disabled = false }: Props) {
    return (
        <div className="flex flex-col gap-4">
            <div className="rounded-xl border border-border/50 bg-muted/30 px-3 py-2.5 sm:px-4">
                <p className="text-sm font-medium text-foreground">{group.label}</p>
                <p className="text-xs leading-relaxed text-muted-foreground">{group.description}</p>
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                {group.endpoints.map((endpoint) => (
                    <ApiPeruConsultaPanel
                        key={endpoint.key}
                        endpoint={endpoint}
                        consultarUrl={consultarUrl}
                        disabled={disabled}
                    />
                ))}
            </div>
        </div>
    );
}
