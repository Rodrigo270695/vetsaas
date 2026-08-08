import { ApiPeruGroupTab } from '../apiperu-group-tab';
import type { ApiPeruGroup } from '../../types';

type Props = {
    group: ApiPeruGroup;
    consultarUrl: string;
    disabled?: boolean;
};

export function VehiculosTab(props: Props) {
    return <ApiPeruGroupTab {...props} />;
}
