import { usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';
import ReportesRoutes from '@/routes/gerente/reportes';

type Props = { filters: { fecha_desde: string; fecha_hasta: string } };

export default function ReportesIndex({ filters }: Props) {
    const currentTeam = usePage<{ currentTeam?: { slug: string } }>().props
        .currentTeam?.slug;
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde);
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta);
    const pdfUrl = currentTeam
        ? ReportesRoutes.comercial.pdf.url(
              { current_team: currentTeam },
              { query: { fecha_desde: fechaDesde, fecha_hasta: fechaHasta } },
          )
        : '#';

    return (
        <GerenteLayout title="Reportes">
            <div className="mx-auto max-w-2xl space-y-6">
                <header>
                    <p className="text-primary-strong text-xs font-bold tracking-[0.18em] uppercase">
                        Gerencia
                    </p>
                    <h1 className="text-foreground mt-1 text-3xl font-bold tracking-tight">
                        Reportes
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Elige el período y descarga el resumen comercial en PDF.
                    </p>
                </header>
                <section className="border-border bg-card rounded-2xl border p-5 shadow-xs sm:p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-foreground grid gap-2 text-sm font-medium">
                            Desde
                            <input
                                type="date"
                                value={fechaDesde}
                                onChange={(event) =>
                                    setFechaDesde(event.target.value)
                                }
                                className="border-border bg-background rounded-lg border px-3 py-2 text-sm"
                            />
                        </label>
                        <label className="text-foreground grid gap-2 text-sm font-medium">
                            Hasta
                            <input
                                type="date"
                                value={fechaHasta}
                                onChange={(event) =>
                                    setFechaHasta(event.target.value)
                                }
                                className="border-border bg-background rounded-lg border px-3 py-2 text-sm"
                            />
                        </label>
                    </div>
                    <a
                        href={pdfUrl}
                        className="bg-primary text-primary-foreground mt-6 inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <FileDown className="size-4" />
                        Descargar PDF
                    </a>
                </section>
            </div>
        </GerenteLayout>
    );
}
