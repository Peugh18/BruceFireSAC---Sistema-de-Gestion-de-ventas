import { Link, usePage } from '@inertiajs/react';
import { Award, Eye, Pencil, Plus } from 'lucide-react';

import { ConfiguracionTabs } from '@/components/configuracion-tabs';
import { Button } from '@/components/ui/button';
import GerenteLayout from '@/layouts/gerente-layout';
import CertificadosRoutes from '@/routes/gerente/configuracion/certificados';
import type { Team } from '@/types';

type Certificado = {
    id: number;
    nombre: string;
    prefijo: string;
    vigencia_meses: number;
    orientacion: 'vertical' | 'horizontal';
    activo: boolean;
    puede_ser_base: boolean;
    vista_previa: string;
};

/**
 * Certificados de la empresa. Todos salen de la misma plantilla: se crean y
 * se cambian en el editor, que muestra la hoja mientras se llena.
 */
export default function Certificados({
    certificados,
}: {
    certificados: Certificado[];
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';

    return (
        <GerenteLayout title="Certificados">
            <div className="flex flex-col gap-5">
                <ConfiguracionTabs teamSlug={equipo} activa="certificados" />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="bg-destructive/10 text-primary-strong flex size-10 items-center justify-center rounded-lg">
                            <Award className="size-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h1 className="text-foreground text-xl font-semibold">
                                Certificados
                            </h1>
                            <p className="text-muted-foreground text-xs">
                                Todos usan la misma plantilla. Crea uno nuevo y
                                lígalo a su tipo de servicio.
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link
                            href={CertificadosRoutes.create.url({
                                current_team: equipo,
                            })}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            Nuevo certificado
                        </Link>
                    </Button>
                </div>

                <ul className="grid gap-3 sm:grid-cols-2">
                    {certificados.map((c) => (
                        <li
                            key={c.id}
                            className="border-border bg-card flex flex-col gap-2 rounded-2xl border p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-foreground font-semibold">
                                        {c.nombre}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        N.° {c.prefijo}-0001 · Hoja{' '}
                                        {c.orientacion} ·{' '}
                                        {c.vigencia_meses > 0
                                            ? `Vigencia ${c.vigencia_meses} meses`
                                            : 'Sin vencimiento'}
                                    </p>
                                </div>
                                {!c.puede_ser_base && (
                                    <span className="bg-muted text-muted-foreground shrink-0 rounded-full px-2 py-0.5 text-xs">
                                        Formato fijo
                                    </span>
                                )}
                            </div>
                            <div className="mt-auto flex gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={CertificadosRoutes.edit.url({
                                            current_team: equipo,
                                            certificado: c.id,
                                        })}
                                    >
                                        <Pencil
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {c.puede_ser_base
                                            ? 'Editar'
                                            : 'Editar textos'}
                                    </Link>
                                </Button>
                                <Button asChild variant="ghost" size="sm">
                                    <a
                                        href={c.vista_previa}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Ver ejemplo
                                    </a>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </GerenteLayout>
    );
}
