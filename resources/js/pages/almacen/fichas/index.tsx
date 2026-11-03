import { pageTitleClass } from '@/components/page-header';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import type { FormEvent } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import FichasRoutes from '@/routes/almacen/fichas';

type CampoFicha = {
    clave: string;
    titulo: string;
    tipo?: 'texto' | 'numero';
    unidad?: string | null;
    obligatorio?: boolean;
};

type ProductoConFicha = {
    id: number;
    codigo: string;
    nombre: string;
    certificado: string;
    campos: CampoFicha[];
    ficha: Record<string, string>;
    pendiente: boolean;
};

type PageProps = {
    currentTeam: { slug: string };
    productos: ProductoConFicha[];
    [key: string]: unknown;
};

/**
 * Almacén llena una sola vez los datos que pide el certificado de cada
 * producto; sirven para todas sus unidades y el certificado sale solo.
 */
export default function FichasTecnicas() {
    const { productos } = usePage<PageProps>().props;
    const pendientes = productos.filter((producto) => producto.pendiente);

    return (
        <AlmacenLayout title="Fichas técnicas">
            <Head title="Fichas técnicas - Almacén" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className={pageTitleClass}>Fichas técnicas</h1>
                    <p className="text-muted-foreground text-sm">
                        Llena una sola vez los datos de cada producto. Sirven
                        para todas sus unidades y su certificado sale solo al
                        venderlo.
                        {pendientes.length === 1 && ' Falta 1 por completar.'}
                        {pendientes.length > 1 &&
                            ` Faltan ${pendientes.length} por completar.`}
                    </p>
                </div>

                {productos.length === 0 ? (
                    <Card>
                        <Empty
                            icon={<ClipboardList />}
                            title="No hay productos con certificado"
                            description="Cuando el Gerente ligue un producto a su certificado, aparecerá aquí para llenar su ficha."
                        />
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {productos.map((producto) => (
                            <FichaDeProducto
                                key={producto.id}
                                producto={producto}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AlmacenLayout>
    );
}

function FichaDeProducto({ producto }: { producto: ProductoConFicha }) {
    const { currentTeam } = usePage<PageProps>().props;
    const form = useForm({
        ficha_tecnica: Object.fromEntries(
            producto.campos.map((campo) => [
                campo.clave,
                producto.ficha[campo.clave] ?? '',
            ]),
        ),
    });
    const errores = form.errors as Record<string, string | undefined>;

    const guardar = (event: FormEvent) => {
        event.preventDefault();
        form.put(
            FichasRoutes.update.url({
                current_team: currentTeam.slug,
                producto: producto.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Card className="p-4">
            <form onSubmit={guardar} className="flex flex-col gap-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h2 className="text-foreground font-semibold">
                            {producto.nombre}
                        </h2>
                        <p className="text-muted-foreground text-xs">
                            {producto.codigo} · {producto.certificado}
                        </p>
                    </div>
                    {producto.pendiente ? (
                        <Badge variant="destructive">Falta completar</Badge>
                    ) : (
                        <Badge variant="secondary">Completa</Badge>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    {producto.campos.map((campo) => {
                        const id = `ficha-${producto.id}-${campo.clave}`;
                        const error = errores[`ficha_tecnica.${campo.clave}`];

                        return (
                            <div key={campo.clave} className="grid gap-1.5">
                                <Label htmlFor={id}>
                                    {campo.titulo}
                                    {campo.unidad ? ` (${campo.unidad})` : ''}
                                    {campo.obligatorio ? ' *' : ''}
                                </Label>
                                <Input
                                    id={id}
                                    value={
                                        form.data.ficha_tecnica[campo.clave] ??
                                        ''
                                    }
                                    onChange={(event) =>
                                        form.setData('ficha_tecnica', {
                                            ...form.data.ficha_tecnica,
                                            [campo.clave]: event.target.value,
                                        })
                                    }
                                    inputMode={
                                        campo.tipo === 'numero'
                                            ? 'decimal'
                                            : undefined
                                    }
                                    required={campo.obligatorio}
                                    maxLength={120}
                                    aria-invalid={!!error}
                                    aria-describedby={
                                        error ? `${id}-error` : undefined
                                    }
                                />
                                {error && (
                                    <p
                                        id={`${id}-error`}
                                        role="alert"
                                        className="text-destructive-strong text-sm dark:text-red-300"
                                    >
                                        {error}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing ? 'Guardando…' : 'Guardar ficha'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}
