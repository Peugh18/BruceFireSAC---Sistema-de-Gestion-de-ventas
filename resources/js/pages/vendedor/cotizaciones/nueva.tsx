import { router, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    FileText,
    Minus,
    Package,
    Plus,
    Trash2,
    Truck,
    Wrench,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Cargando } from '@/components/cargando';
import CatalogPicker, { type CatalogItem } from '@/components/catalog-picker';
import ClientPicker, { type ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import cotizaciones from '@/routes/vendedor/cotizaciones';
import type { Team } from '@/types';

type QuoteItemForm = {
    key: string;
    tipo: 'product' | 'service';
    product_id?: number;
    service_id?: number;
    codigo: string | null;
    nombre: string;
    cantidad: number;
    precio_unitario: number;
    descuento: number;
};

type QuoteFormData = {
    client_id: number | '';
    fecha: string;
    vigencia_hasta: string;
    referencia: string;
    condicion_pago_propuesta: string;
    observaciones: string;
    items: QuoteItemForm[];
};

type Renovacion = {
    numero: string;
    client: ClientFicha;
    referencia: string | null;
    condicion_pago_propuesta: string | null;
    observaciones: string | null;
    items: Omit<QuoteItemForm, 'key'>[];
};

function sumarDias(dias: number) {
    const date = new Date();
    date.setDate(date.getDate() + dias);

    return date.toISOString().slice(0, 10);
}

function money(value: number) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(value || 0);
}

function fieldError(errors: Partial<Record<string, string>>, key: string) {
    return errors[key] ? (
        <p className="text-destructive mt-1 text-[11px] font-semibold">
            {errors[key]}
        </p>
    ) : null;
}

let secuencia = 0;

const VIGENCIAS = [7, 15, 30];

export default function NuevaCotizacion({
    renovacion = null,
}: {
    renovacion?: Renovacion | null;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const form = useForm<QuoteFormData>({
        client_id: renovacion?.client.id ?? '',
        fecha: sumarDias(0),
        vigencia_hasta: sumarDias(15),
        referencia: renovacion?.referencia ?? '',
        condicion_pago_propuesta:
            renovacion?.condicion_pago_propuesta ?? 'Contado',
        observaciones: renovacion?.observaciones ?? '',
        items:
            renovacion?.items.map((item) => ({
                ...item,
                key: `q${++secuencia}`,
            })) ?? [],
    });

    const [cliente, setCliente] = useState<ClientFicha | null>(
        renovacion?.client ?? null,
    );
    const [vigenciaDias, setVigenciaDias] = useState(15);
    const [destino, setDestino] = useState<'local_cliente' | 'vehiculo'>(
        'local_cliente',
    );

    const setItems = (items: QuoteItemForm[]) => form.setData('items', items);

    const actualizar = (key: string, cambio: Partial<QuoteItemForm>) =>
        setItems(
            form.data.items.map((i) =>
                i.key === key ? { ...i, ...cambio } : i,
            ),
        );

    const agregar = (item: CatalogItem) => {
        const existente = form.data.items.find(
            (i) =>
                i.tipo === item.tipo &&
                (i.product_id ?? i.service_id) === item.id,
        );

        if (existente) {
            actualizar(existente.key, { cantidad: existente.cantidad + 1 });
            return;
        }

        setItems([
            ...form.data.items,
            {
                key: `q${++secuencia}`,
                tipo: item.tipo,
                product_id: item.tipo === 'product' ? item.id : undefined,
                service_id: item.tipo === 'service' ? item.id : undefined,
                codigo: item.codigo,
                nombre: item.nombre,
                cantidad: 1,
                precio_unitario: item.precio_venta,
                descuento: 0,
            },
        ]);
    };

    const elegirVigencia = (dias: number) => {
        setVigenciaDias(dias);
        form.setData('vigencia_hasta', sumarDias(dias));
    };

    // Precios con IGV incluido: se separa la base línea por línea.
    let base = 0;
    let total = 0;
    for (const item of form.data.items) {
        const t =
            Math.round(
                (item.cantidad * item.precio_unitario - item.descuento) * 100,
            ) / 100;
        base += Math.round((t / 1.18) * 100) / 100;
        total += t;
    }
    const igv = Math.round((total - base) * 100) / 100;

    const submitQuote = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(cotizaciones.store.url(teamSlug), { preserveScroll: true });
    };

    return (
        <VendedorLayout title="Nueva cotización">
            {renovacion && (
                <div className="text-foreground mb-4 rounded-[12px] border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm">
                    Estás renovando la cotización {renovacion.numero}. Revisa
                    los datos y guarda la nueva cotización; tendrá 15 días de
                    vigencia.
                </div>
            )}
            <form
                onSubmit={submitQuote}
                className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <ClientPicker
                                teamSlug={teamSlug}
                                value={cliente}
                                onChange={(ficha) => {
                                    setCliente(ficha);
                                    form.setData((data) => ({
                                        ...data,
                                        client_id: ficha?.id ?? '',
                                        referencia: '',
                                    }));
                                }}
                                error={form.errors.client_id}
                                autoFocus
                            />

                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Precio válido por
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    {VIGENCIAS.map((dias) => (
                                        <button
                                            key={dias}
                                            type="button"
                                            onClick={() => elegirVigencia(dias)}
                                            className={`flex-1 rounded-[7px] px-3 py-1.5 text-xs font-bold transition-all ${vigenciaDias === dias ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {dias} días
                                        </button>
                                    ))}
                                </div>
                                <p className="text-muted-foreground mt-1.5 text-[11.5px]">
                                    Emitida hoy{' '}
                                    {new Date().toLocaleDateString('es-PE')}. Se
                                    respeta el precio hasta el{' '}
                                    <b className="text-foreground">
                                        {new Date(
                                            `${form.data.vigencia_hasta}T00:00:00`,
                                        ).toLocaleDateString('es-PE')}
                                    </b>
                                    .
                                </p>
                                {fieldError(form.errors, 'vigencia_hasta')}
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[180px_minmax(0,1fr)]">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Para
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    {(
                                        [
                                            [
                                                'local_cliente',
                                                'Local',
                                                Building2,
                                            ],
                                            ['vehiculo', 'Vehículo', Truck],
                                        ] as const
                                    ).map(([valor, texto, Icono]) => (
                                        <button
                                            key={valor}
                                            type="button"
                                            onClick={() => {
                                                setDestino(valor);
                                                form.setData('referencia', '');
                                            }}
                                            className={`flex flex-1 items-center justify-center gap-1 rounded-[7px] px-3 py-1.5 text-xs font-bold transition-all ${destino === valor ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            <Icono className="size-3" />
                                            {texto}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <ReferenciaField
                                cliente={cliente}
                                destino={destino}
                                value={form.data.referencia}
                                onChange={(valor) =>
                                    form.setData('referencia', valor)
                                }
                                error={form.errors.referencia}
                            />
                        </div>
                    </Card>

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Package className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Productos y servicios
                                </h2>
                                <p className="text-muted-foreground text-[12px]">
                                    Busca por nombre o código, o escanea el
                                    código de barras. Precios con IGV incluido.
                                </p>
                            </div>
                        </div>

                        <CatalogPicker teamSlug={teamSlug} onPick={agregar} />
                        {fieldError(form.errors, 'items')}

                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        {[
                                            'Ítem',
                                            'Cant.',
                                            'P. unit.',
                                            'Desc.',
                                            'Total',
                                            '',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] uppercase"
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.items.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="text-muted-foreground px-2.5 py-10 text-center"
                                            >
                                                Agrega un producto o servicio
                                                para empezar.
                                            </td>
                                        </tr>
                                    ) : (
                                        form.data.items.map((item) => (
                                            <tr key={item.key}>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <span className="text-foreground flex items-center gap-2 font-semibold">
                                                        {item.tipo ===
                                                        'service' ? (
                                                            <Wrench className="size-3.5 text-blue-600 dark:text-blue-400" />
                                                        ) : (
                                                            <Package className="text-muted-foreground size-3.5" />
                                                        )}
                                                        {item.nombre}
                                                    </span>
                                                    <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                        {item.codigo ?? '—'}
                                                    </span>
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <div className="flex items-center gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                actualizar(
                                                                    item.key,
                                                                    {
                                                                        cantidad:
                                                                            Math.max(
                                                                                1,
                                                                                item.cantidad -
                                                                                    1,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                            className="border-border hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border"
                                                        >
                                                            <Minus className="size-3" />
                                                        </button>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            value={
                                                                item.cantidad
                                                            }
                                                            onChange={(e) =>
                                                                actualizar(
                                                                    item.key,
                                                                    {
                                                                        cantidad:
                                                                            Math.max(
                                                                                1,
                                                                                Number(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                    1,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                            className="border-border h-8 w-14 rounded-[7px] text-center text-[12px]"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                actualizar(
                                                                    item.key,
                                                                    {
                                                                        cantidad:
                                                                            item.cantidad +
                                                                            1,
                                                                    },
                                                                )
                                                            }
                                                            className="border-border hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border"
                                                        >
                                                            <Plus className="size-3" />
                                                        </button>
                                                    </div>
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={
                                                            item.precio_unitario
                                                        }
                                                        onChange={(e) =>
                                                            actualizar(
                                                                item.key,
                                                                {
                                                                    precio_unitario:
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                },
                                                            )
                                                        }
                                                        className="border-border h-8 w-24 rounded-[7px] text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={item.descuento}
                                                        onChange={(e) =>
                                                            actualizar(
                                                                item.key,
                                                                {
                                                                    descuento:
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                },
                                                            )
                                                        }
                                                        className="border-border h-8 w-20 rounded-[7px] text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5 font-['IBM_Plex_Mono',monospace] font-bold">
                                                    {money(
                                                        item.cantidad *
                                                            item.precio_unitario -
                                                            item.descuento,
                                                    )}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        onClick={() =>
                                                            setItems(
                                                                form.data.items.filter(
                                                                    (i) =>
                                                                        i.key !==
                                                                        item.key,
                                                                ),
                                                            )
                                                        }
                                                        className="border-border bg-card text-destructive size-7 rounded-[7px] shadow-none"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <aside className="flex flex-col gap-4 xl:sticky xl:top-4 xl:self-start">
                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex items-center gap-3">
                            <div className="bg-destructive/10 text-primary flex size-10 items-center justify-center rounded-[11px]">
                                <ClipboardList className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Resumen
                                </h2>
                                <p className="text-muted-foreground truncate text-[12px]">
                                    {cliente?.razon_social ??
                                        'Cliente pendiente'}
                                </p>
                            </div>
                        </div>

                        <div className="bg-muted/40 space-y-2 rounded-[12px] p-4 text-[13px]">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Gravado
                                </span>
                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                    {money(base)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    IGV 18% (incluido)
                                </span>
                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                    {money(igv)}
                                </span>
                            </div>
                            <div className="border-border border-t pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-foreground font-bold">
                                        Total
                                    </span>
                                    <span className="text-foreground font-['Oswald',sans-serif] text-[28px] font-semibold">
                                        {money(total)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Condición de pago propuesta
                            </Label>
                            <div className="mt-1 flex flex-wrap gap-1.5">
                                {[
                                    'Contado',
                                    'Crédito 7 días',
                                    'Crédito 15 días',
                                    'Crédito 30 días',
                                ].map((opcion) => (
                                    <button
                                        key={opcion}
                                        type="button"
                                        onClick={() =>
                                            form.setData(
                                                'condicion_pago_propuesta',
                                                opcion,
                                            )
                                        }
                                        className={`rounded-full border px-2.5 py-0.5 text-[11.5px] font-bold ${form.data.condicion_pago_propuesta === opcion ? 'border-primary bg-destructive/10 text-primary' : 'border-border text-muted-foreground hover:text-foreground'}`}
                                    >
                                        {opcion}
                                    </button>
                                ))}
                            </div>
                            {fieldError(
                                form.errors,
                                'condicion_pago_propuesta',
                            )}
                        </div>

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Observaciones
                            </Label>
                            <textarea
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card mt-1 min-h-[70px] w-full rounded-[9px] border px-3 py-2 text-[13px] outline-none"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary/90 h-11 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            {form.processing ? (
                                <Cargando className="size-4" />
                            ) : (
                                <FileText className="size-4" />
                            )}
                            Guardar cotización
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit(cotizaciones.index.url(teamSlug))
                            }
                            className="border-border bg-card text-foreground/80 h-10 rounded-[9px] shadow-none"
                        >
                            Cancelar
                        </Button>
                    </Card>
                </aside>
            </form>
        </VendedorLayout>
    );
}
