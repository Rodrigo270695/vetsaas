import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { FormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const ROUTE_URL = '/comunicaciones/campanas';

const DEFAULT_VARIANTES = [
    'Hola {nombre}, te escribimos de {clinica} por la campaña de desparasitación de {mascota}. ¿Agendamos un turno esta semana?',
    'Hola {nombre_completo}, en {clinica} estamos con desparasitación para {mascota}. Si te interesa, responde este WhatsApp y te ayudamos a coordinar.',
    '{nombre}, te recordamos la campaña de desparasitación en {clinica} para {mascota}. Cupos limitados; avísanos y te damos horarios.',
];

type CampanaForm = {
    id: string;
    nombre: string;
    variantes: string[];
    tope_diario: number;
    intervalo_minutos: number;
    hora_inicio: string;
    hora_fin: string;
    imagen_url: string | null;
};

type Props = {
    campana?: CampanaForm | null;
};

export default function CampanaFormPage({ campana = null }: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { errors } = usePage().props as { errors: Record<string, string> };
    const editing = campana !== null;

    const [nombre, setNombre] = useState(campana?.nombre ?? '');
    const [variantes, setVariantes] = useState<string[]>(
        campana?.variantes?.length ? campana.variantes : DEFAULT_VARIANTES,
    );
    const [tope, setTope] = useState(String(campana?.tope_diario ?? 50));
    const [intervalo, setIntervalo] = useState(String(campana?.intervalo_minutos ?? 12));
    const [horaInicio, setHoraInicio] = useState(
        String(campana?.hora_inicio ?? '09:00').slice(0, 5),
    );
    const [horaFin, setHoraFin] = useState(String(campana?.hora_fin ?? '18:00').slice(0, 5));
    const [imagen, setImagen] = useState<File | null>(null);
    const [clearImagen, setClearImagen] = useState(false);
    const [sending, setSending] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSending(true);
        const payload: Record<string, unknown> = {
            nombre,
            variantes,
            tope_diario: Number(tope),
            intervalo_minutos: Number(intervalo),
            hora_inicio: horaInicio,
            hora_fin: horaFin,
            clear_imagen: clearImagen ? 1 : 0,
        };
        if (imagen) {
            payload.imagen = imagen;
        }

        router.post(editing ? `${ROUTE_URL}/${campana.id}` : ROUTE_URL, payload, {
            forceFormData: true,
            onFinish: () => setSending(false),
        });
    };

    return (
        <>
            <Head
                title={
                    editing ? t('campanas.form_title_edit') : t('campanas.form_title_create')
                }
            />
            <form
                className="flex flex-1 flex-col gap-5 p-4 sm:p-6"
                onSubmit={submit}
            >
                <PageHeader
                    title={
                        editing
                            ? t('campanas.form_title_edit')
                            : t('campanas.form_title_create')
                    }
                    description={t('campanas.form_description')}
                    action={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ROUTE_URL}>{t('common:actions.back')}</Link>
                        </Button>
                    }
                />

                <FormField id="nombre" label={t('campanas.nombre')} required error={errors.nombre}>
                    <Input
                        id="nombre"
                        value={nombre}
                        onChange={(e) => setNombre(e.target.value)}
                        placeholder={t('campanas.nombre_placeholder')}
                    />
                </FormField>

                {variantes.map((texto, index) => (
                    <FormField
                        key={index}
                        id={`variante-${index}`}
                        label={t('campanas.variante', { n: index + 1 })}
                        required
                        error={errors[`variantes.${index}`] ?? (index === 0 ? errors.variantes : undefined)}
                    >
                        <Textarea
                            id={`variante-${index}`}
                            value={texto}
                            onChange={(e) => {
                                const next = [...variantes];
                                next[index] = e.target.value;
                                setVariantes(next);
                            }}
                            rows={4}
                        />
                        {variantes.length > 3 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="mt-1 cursor-pointer"
                                onClick={() =>
                                    setVariantes(variantes.filter((_, i) => i !== index))
                                }
                            >
                                {t('campanas.remove_variante')}
                            </Button>
                        ) : null}
                    </FormField>
                ))}

                {variantes.length < 5 ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit cursor-pointer"
                        onClick={() => setVariantes([...variantes, ''])}
                    >
                        {t('campanas.add_variante')}
                    </Button>
                ) : null}

                <FormField id="imagen" label={t('campanas.imagen')} hint={t('campanas.imagen_hint')} error={errors.imagen}>
                    <Input
                        id="imagen"
                        type="file"
                        accept="image/*"
                        onChange={(e) => setImagen(e.target.files?.[0] ?? null)}
                    />
                    {campana?.imagen_url && !clearImagen ? (
                        <label className="mt-2 flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={clearImagen}
                                onChange={(e) => setClearImagen(e.target.checked)}
                            />
                            {t('campanas.clear_imagen')}
                        </label>
                    ) : null}
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField id="tope" label={t('campanas.tope')} required>
                        <Input
                            id="tope"
                            type="number"
                            min={50}
                            max={100}
                            value={tope}
                            onChange={(e) => setTope(e.target.value)}
                        />
                    </FormField>
                    <FormField id="intervalo" label={t('campanas.intervalo')} required>
                        <Input
                            id="intervalo"
                            type="number"
                            min={8}
                            max={30}
                            value={intervalo}
                            onChange={(e) => setIntervalo(e.target.value)}
                        />
                    </FormField>
                    <FormField id="hora_inicio" label={t('campanas.hora_inicio')} required>
                        <Input
                            id="hora_inicio"
                            type="time"
                            value={horaInicio}
                            onChange={(e) => setHoraInicio(e.target.value)}
                        />
                    </FormField>
                    <FormField id="hora_fin" label={t('campanas.hora_fin')} required>
                        <Input
                            id="hora_fin"
                            type="time"
                            value={horaFin}
                            onChange={(e) => setHoraFin(e.target.value)}
                        />
                    </FormField>
                </div>

                <div>
                    <Button type="submit" disabled={sending} className="cursor-pointer">
                        {t('campanas.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

CampanaFormPage.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Campañas', href: ROUTE_URL },
        { title: 'Nueva', href: `${ROUTE_URL}/create` },
    ],
};
