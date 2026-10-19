import { Head, Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import GerenteLayout from '@/layouts/gerente-layout';

type Props = {
    titulo: string;
    campos: Record<string, string>;
    filas: { descripcion: string; detalle: string }[];
    enlaces: { titulo: string; url: string }[];
};

export default function FichaGerente({
    titulo,
    campos,
    filas,
    enlaces,
}: Props) {
    return (
        <GerenteLayout title={titulo}>
            <Head title={titulo} />
            <div className="mx-auto w-full max-w-4xl space-y-4 p-4 sm:p-6">
                <h1 className="text-foreground text-2xl font-semibold">
                    {titulo}
                </h1>
                <Card className="p-4">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        {Object.entries(campos).map(([nombre, valor]) => (
                            <div key={nombre}>
                                <dt className="text-muted-foreground text-sm">
                                    {nombre}
                                </dt>
                                <dd className="text-foreground font-medium break-words">
                                    {valor}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Card>
                {filas.length > 0 && (
                    <Card className="divide-border divide-y p-4">
                        {filas.map((fila, indice) => (
                            <div className="py-3" key={indice}>
                                <p className="text-foreground font-medium">
                                    {fila.descripcion}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {fila.detalle}
                                </p>
                            </div>
                        ))}
                    </Card>
                )}
                <div className="flex flex-wrap gap-3">
                    {enlaces.map((enlace) => (
                        <Button asChild variant="outline" key={enlace.url}>
                            <Link href={enlace.url}>{enlace.titulo}</Link>
                        </Button>
                    ))}
                </div>
            </div>
        </GerenteLayout>
    );
}
