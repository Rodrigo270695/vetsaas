export type PlantillaGrupo =
    | 'citas'
    | 'vacunas'
    | 'cumple'
    | 'grooming'
    | 'hotel';

export type PlantillaItem = {
    id: string;
    tipo: string;
    grupo: PlantillaGrupo | string;
    canal: string;
    cuerpo: string;
    cuerpo_default: string;
    activo: boolean;
    orden: number;
    variables: string[];
    preview: string;
    updated_at: string | null;
};

export type PlantillasPageProps = {
    groups: Record<string, PlantillaItem[]>;
    groupOrder: string[];
};
