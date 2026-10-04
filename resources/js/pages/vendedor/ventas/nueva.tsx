import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CalendarClock,
    CreditCard,
    FileText,
    MapPin,
    Minus,
    Plus,
    ScanLine,
    Trash2,
    Truck,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import { Cargando } from '@/components/cargando';
import CatalogPicker, {
    type CatalogItem,
    type CatalogUnit,
} from '@/components/catalog-picker';
import ClientPicker, {
    type ClientFicha,
    type ClientOption,
} from '@/components/client-picker';
import CreditoDialog, { type Cuota } from '@/components/credito-dialog';
import ReferenciaField from '@/components/referencia-field';
import UnidadesDialog from '@/components/unidades-dialog';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import { leerCookie } from '@/lib/cookies';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';
import { fechaLocal } from '@/lib/utils';

type SedeOption = { id: number; nombre: string };

type LineType = 'unidad_nueva' | 'recarga_servicio' | 'producto' | 'servicio';
type Destination = 'local_cliente' | 'vehiculo';
type PaymentCondition = 'contado' | 'credito';
type DocumentType = 'factura' | 'boleta' | 'nota_venta';

type SaleLine = {
    key: string;
    tipo_linea: LineType;
    numero_serie?: string;
    product_id?: number;
    service_id?: number;
    inventory_unit_id?: number;
    nombre: string;
    detalle?: string;
    cantidad: number;
    precio_unitario: number;
    descuento: number;
    stock?: number | null;
};

type MedioPago =
    | 'efectivo'
    | 'yape'
    | 'plin'
    | 'transferencia'
    | 'pos'
    | 'deposito';

/** Cómo paga el cliente al contado. No va a SUNAT: registra el cobro en caja. */
const MEDIOS_PAGO: { valor: MedioPago; texto: string }[] = [
    { valor: 'efectivo', texto: 'Efectivo' },
    { valor: 'yape', texto: 'Yape' },
    { valor: 'plin', texto: 'Plin' },
    { valor: 'transferencia', texto: 'Transferencia' },
    { valor: 'pos', texto: 'Tarjeta' },
    { valor: 'deposito', texto: 'Depósito' },
];

/** Ley 28194: desde S/ 2,000 el pago debe ir por banco para el crédito fiscal. */
const LIMITE_BANCARIZACION = 2000;

type SaleFormData = {
    service_order_id: number | '';
    client_id: number | '';
    sede_id: number | '';
    quote_id: number | '';
    fecha: string;
    destino: Destination;
    referencia: string;
    condicion_pago: PaymentCondition;
    medio_pago: MedioPago;
    numero_operacion: string;
    cuotas: Cuota[];
    comprobante_tipo: DocumentType;
    observaciones: string;
    items: SaleLine[];
};

type QuoteLine = {
    tipo: 'product' | 'service';
    product_id: number | null;
    service_id: number | null;
    nombre: string;
    codigo: string | null;
    serializado: boolean;
    cantidad: number;
    precio_unitario: number;
};

type QuoteOption = {
    id: number;
    numero: string;
    client: ClientOption;
    items: QuoteLine[];
    referencia: string | null;
};

/**
 * Venta ya existente: para editarla (con id; un borrador o una ya emitida
 * que SUNAT aún no aceptó) o una copia para rehacerla (sin id).
 */
type VentaPrefill = {
    id: number | null;
    service_order_id?: number;
    numero_interno: string;
    emitida?: boolean;
    comprobante?: string | null;
    rechazado?: boolean;
    fecha?: string | null;
    client: ClientOption;
    sede_id: number | null;
    destino: Destination;
    referencia: string | null;
    condicion_pago: PaymentCondition;
    medio_pago: MedioPago;
    numero_operacion: string | null;
    comprobante_tipo: DocumentType;
    observaciones: string | null;
    cuotas: Cuota[];
    items: Omit<SaleLine, 'key'>[];
};

type Props = {
    clientesVarios: ClientOption;
    limiteBoletaSinIdentificar: number;
    sedes: SedeOption[];
    quote: QuoteOption | null;
    venta: VentaPrefill | null;
    caja_abierta?: boolean;
};

const NOMBRE_COMPROBANTE: Record<DocumentType, string> = {
    factura: 'factura',
    boleta: 'boleta',
    nota_venta: 'nota de venta',
};

function today() {
    return fechaLocal();
}

function money(value: number) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(value || 0);
}

function fieldError(errors: Partial<Record<string, string>>, key: string) {
    return errors[key] ? (
        <p className="text-destructive-strong mt-1 text-[11px] font-semibold">
            {errors[key]}
        </p>
    ) : null;
}

function SegmentButton({
    active,
    children,
    onClick,
    disabled = false,
    title,
}: {
    active: boolean;
    children: React.ReactNode;
    onClick: () => void;
    disabled?: boolean;
    title?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={`flex-1 rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all disabled:cursor-not-allowed disabled:opacity-40 ${
                active
                    ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                    : 'text-muted-foreground hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

let secuencia = 0;
const nuevaClave = () => `l${++secuencia}`;

/**
 * Los precios ya incluyen IGV: el total es lo que paga el cliente y la base
 * e IGV se separan línea por línea, igual que en el backend.
 */
function desglosar(lineas: SaleLine[]) {
    let base = 0;
    let total = 0;

    for (const linea of lineas) {
        const t =
            Math.round(
                (linea.cantidad * linea.precio_unitario - linea.descuento) *
                    100,
            ) / 100;
        base += Math.round((t / 1.18) * 100) / 100;
        total += t;
    }

    return {
        base: Math.round(base * 100) / 100,
        igv: Math.round((total - base) * 100) / 100,
        total: Math.round(total * 100) / 100,
    };
}

export default function NuevaVenta({
    clientesVarios,
    limiteBoletaSinIdentificar,
    sedes,
    quote,
    venta,
    caja_abierta,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    // La sede es la del vendedor: si solo tiene una, se usa sola.
    const sedeFija = sedes.length === 1 ? sedes[0] : null;

    const editando = venta?.id != null;
    const editandoEmitida = editando && venta?.emitida === true;
    const clienteInicial = venta?.client ?? quote?.client ?? null;

    const form = useForm<SaleFormData>({
        service_order_id: venta?.service_order_id ?? '',
        client_id: clienteInicial?.id ?? '',
        sede_id: sedeFija?.id ?? venta?.sede_id ?? '',
        quote_id: quote?.id ?? '',
        fecha: venta?.fecha ?? today(),
        destino: venta?.destino ?? 'local_cliente',
        referencia: venta?.referencia ?? quote?.referencia ?? '',
        condicion_pago: venta?.condicion_pago ?? 'contado',
        medio_pago: venta?.medio_pago ?? 'efectivo',
        numero_operacion: venta?.numero_operacion ?? '',
        cuotas: venta?.cuotas ?? [],
        comprobante_tipo:
            venta?.comprobante_tipo ??
            (clienteInicial?.tipo_documento &&
            clienteInicial.tipo_documento !== 'ruc'
                ? 'boleta'
                : 'factura'),
        observaciones: venta?.observaciones ?? '',
        items: venta
            ? venta.items.map((line) => ({ ...line, key: nuevaClave() }))
            : (quote?.items ?? [])
                  .filter((line) => !line.serializado)
                  .map((line) => ({
                      key: nuevaClave(),
                      tipo_linea:
                          line.tipo === 'service' ? 'servicio' : 'producto',
                      product_id: line.product_id ?? undefined,
                      service_id: line.service_id ?? undefined,
                      nombre: line.nombre,
                      cantidad: line.cantidad,
                      precio_unitario: line.precio_unitario,
                      descuento: 0,
                  })),
    });

    const [cliente, setCliente] = useState<ClientFicha | null>(
        clienteInicial ? { ...clienteInicial, vehiculos: [], sedes: [] } : null,
    );
    const [productoConSerie, setProductoConSerie] =
        useState<CatalogItem | null>(null);
    const [creditoAbierto, setCreditoAbierto] = useState(false);
    const [aviso, setAviso] = useState<string | null>(null);

    // Si viene de una cotización o de otra venta, completa la ficha (placas y sedes).
    useEffect(() => {
        if (!clienteInicial) {
            return;
        }

        void fetch(
            clientes.ficha.url({
                current_team: teamSlug,
                client: clienteInicial.id,
            }),
        )
            .then((r) => r.json())
            .then((ficha: ClientFicha) => setCliente(ficha));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const { base, igv, total } = desglosar(form.data.items);

    const clienteSinRuc =
        cliente !== null &&
        cliente.tipo_documento !== undefined &&
        cliente.tipo_documento !== 'ruc';
    const esClientesVarios = cliente?.id === clientesVarios.id;
    const boletaSuperaLimite =
        esClientesVarios &&
        form.data.comprobante_tipo === 'boleta' &&
        total > limiteBoletaSinIdentificar;

    // La factura exige la dirección fiscal del cliente (sale impresa): si
    // falta, se completa aquí mismo y queda guardada en su ficha.
    const facturaSinDireccion =
        form.data.comprobante_tipo === 'factura' &&
        cliente !== null &&
        cliente.tipo_documento === 'ruc' &&
        (cliente.direccion_fiscal ?? '').replace(/[-.\s]/g, '') === '';
    const [direccionNueva, setDireccionNueva] = useState('');
    const [guardandoDireccion, setGuardandoDireccion] = useState(false);
    const [errorDireccion, setErrorDireccion] = useState<string | null>(null);

    const guardarDireccion = async () => {
        if (!cliente) {
            return;
        }

        setGuardandoDireccion(true);
        setErrorDireccion(null);

        try {
            const response = await fetch(
                clientes.direccion.url({
                    current_team: teamSlug,
                    client: cliente.id,
                }),
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
                    },
                    body: JSON.stringify({ direccion_fiscal: direccionNueva }),
                },
            );
            const payload = (await response.json()) as {
                direccion_fiscal?: string;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                setErrorDireccion(
                    payload.errors?.direccion_fiscal?.[0] ??
                        payload.message ??
                        'No se pudo guardar la dirección.',
                );
                return;
            }

            setCliente({
                ...cliente,
                direccion_fiscal: payload.direccion_fiscal ?? direccionNueva,
            });
            setDireccionNueva('');
            setAviso(null);
        } catch {
            setErrorDireccion('No se pudo guardar la dirección.');
        } finally {
            setGuardandoDireccion(false);
        }
    };

    const elegirCliente = (ficha: ClientFicha | null) => {
        setCliente(ficha);
        form.setData((data) => ({
            ...data,
            client_id: ficha?.id ?? '',
            referencia:
                ficha && ficha.id === data.client_id ? data.referencia : '',
            comprobante_tipo:
                ficha &&
                ficha.tipo_documento !== 'ruc' &&
                data.comprobante_tipo === 'factura'
                    ? 'boleta'
                    : data.comprobante_tipo,
        }));
    };

    const setItems = (items: SaleLine[]) => form.setData('items', items);

    const actualizarLinea = (key: string, cambio: Partial<SaleLine>) =>
        setItems(
            form.data.items.map((l) => {
                if (l.key !== key) return l;
                const updated = { ...l, ...cambio };
                const maxDescuento = updated.cantidad * updated.precio_unitario;
                if (updated.descuento > maxDescuento) {
                    updated.descuento = Math.max(0, maxDescuento);
                }
                return updated;
            }),
        );

    const precioCotizado = (productId?: number, serviceId?: number) =>
        quote?.items.find(
            (l) =>
                (productId && l.product_id === productId) ||
                (serviceId && l.service_id === serviceId),
        )?.precio_unitario;

    const agregarUnidades = (unidades: CatalogUnit[]) => {
        const nuevas = unidades.filter(
            (u) =>
                !form.data.items.some((l) => l.numero_serie === u.numero_serie),
        );

        if (nuevas.length < unidades.length) {
            setAviso(
                'Algunas series ya estaban en la venta y no se repitieron.',
            );
        }

        setItems([
            ...form.data.items,
            ...nuevas.map((u) => ({
                key: nuevaClave(),
                tipo_linea: 'unidad_nueva' as const,
                numero_serie: u.numero_serie,
                product_id: u.product_id,
                inventory_unit_id: u.inventory_unit_id,
                nombre: u.nombre,
                detalle: [u.capacidad, u.marca].filter(Boolean).join(' · '),
                cantidad: 1,
                precio_unitario: precioCotizado(u.product_id) ?? u.precio_venta,
                descuento: 0,
            })),
        ]);
    };

    const agregarItem = (item: CatalogItem) => {
        setAviso(null);

        if (item.tipo === 'product' && item.serializado) {
            if (!form.data.sede_id) {
                setAviso('Elige la sede antes de agregar productos con serie.');
                return;
            }
            setProductoConSerie(item);
            return;
        }

        const tipo: LineType =
            item.tipo === 'service' ? 'servicio' : 'producto';
        const existente = form.data.items.find(
            (l) =>
                l.tipo_linea === tipo &&
                (tipo === 'servicio'
                    ? l.service_id === item.id
                    : l.product_id === item.id),
        );

        // Lo que no hay en el almacén no se vende: se avisa al escanear y no
        // al guardar.
        const cantidadNueva = (existente?.cantidad ?? 0) + 1;
        if (
            tipo === 'producto' &&
            item.stock !== null &&
            cantidadNueva > item.stock
        ) {
            setAviso(
                item.stock > 0
                    ? `Solo hay ${item.stock} de ${item.nombre} en el almacén de esta sede.`
                    : `No hay ${item.nombre} en el almacén de esta sede.`,
            );
            return;
        }

        if (existente) {
            actualizarLinea(existente.key, {
                cantidad: cantidadNueva,
            });
            return;
        }

        setItems([
            ...form.data.items,
            {
                key: nuevaClave(),
                tipo_linea: tipo,
                product_id: tipo === 'producto' ? item.id : undefined,
                service_id: tipo === 'servicio' ? item.id : undefined,
                nombre: item.nombre,
                cantidad: 1,
                precio_unitario:
                    precioCotizado(
                        tipo === 'producto' ? item.id : undefined,
                        tipo === 'servicio' ? item.id : undefined,
                    ) ?? item.precio_venta,
                descuento: 0,
                stock: item.stock,
            },
        ]);
    };

    const cambiarCondicion = (condicion: PaymentCondition) => {
        form.setData((data) => ({
            ...data,
            condicion_pago: condicion,
            cuotas: condicion === 'contado' ? [] : data.cuotas,
            numero_operacion:
                condicion === 'credito' ? '' : data.numero_operacion,
        }));

        if (condicion === 'credito') {
            setCreditoAbierto(true);
        }
    };

    const submitSale = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // "Emitir" es el botón principal; "Guardar borrador" deja la venta sin emitir.
        const emitir =
            editandoEmitida ||
            (event.nativeEvent as SubmitEvent).submitter?.dataset.accion !==
                'borrador';

        const unidadFaltante = quote?.items
            .filter((line) => line.serializado)
            .map((line) => ({
                nombre: line.nombre,
                faltantes:
                    line.cantidad -
                    form.data.items.filter(
                        (item) => item.product_id === line.product_id,
                    ).length,
            }))
            .find((line) => line.faltantes > 0);

        if (emitir && facturaSinDireccion) {
            setAviso(
                'Completa la dirección fiscal del cliente: la factura la necesita.',
            );
            return;
        }

        if (emitir && unidadFaltante) {
            setAviso(
                `Faltan ${unidadFaltante.faltantes} ${unidadFaltante.faltantes === 1 ? 'unidad' : 'unidades'} por escanear de ${unidadFaltante.nombre}.`,
            );
            return;
        }

        if (
            form.data.condicion_pago === 'credito' &&
            (creditoIncompleto || cuotasDesactualizadas)
        ) {
            setCreditoAbierto(true);
            setAviso(
                creditoIncompleto
                    ? 'Define al menos una cuota antes de emitir la venta a crédito.'
                    : 'El total de las cuotas debe coincidir con el total de la venta.',
            );
            return;
        }

        if (
            caja_abierta === false &&
            emitir &&
            form.data.condicion_pago === 'contado' &&
            form.data.medio_pago === 'efectivo'
        ) {
            setAviso(
                'No tienes una caja abierta. Abre tu turno en Caja o elige otro medio de pago para emitir la venta.',
            );
            return;
        }

        form.transform((data) => ({
            ...data,
            emitir,
            cuotas: data.condicion_pago === 'credito' ? data.cuotas : [],
            medio_pago:
                data.condicion_pago === 'contado' ? data.medio_pago : null,
            numero_operacion:
                data.condicion_pago === 'contado' &&
                data.medio_pago !== 'efectivo'
                    ? data.numero_operacion
                    : '',
            items: data.items.map((l) => ({
                tipo_linea: l.tipo_linea,
                numero_serie: l.numero_serie ?? null,
                product_id: l.product_id ?? null,
                service_id: l.service_id ?? null,
                cantidad: l.cantidad,
                precio_unitario: l.precio_unitario,
                descuento: l.descuento,
            })),
        }));
        if (editando && venta?.id) {
            form.put(
                ventas.update.url({ current_team: teamSlug, sale: venta.id }),
                { preserveScroll: true },
            );

            return;
        }

        form.post(ventas.store.url(teamSlug), { preserveScroll: true });
    };

    const creditoIncompleto =
        form.data.condicion_pago === 'credito' && form.data.cuotas.length === 0;
    const cuotasDesactualizadas =
        form.data.condicion_pago === 'credito' &&
        form.data.cuotas.length > 0 &&
        Math.abs(form.data.cuotas.reduce((s, c) => s + c.monto, 0) - total) >=
            0.01;

    return (
        <VendedorLayout
            title={
                editando
                    ? `Editar ${venta?.numero_interno ?? 'venta'}`
                    : 'Nueva venta'
            }
        >
            <div className="mb-4">
                <PageHeader
                    title={
                        editando
                            ? `Editar ${venta?.numero_interno ?? 'venta'}`
                            : 'Nueva venta'
                    }
                    description="Elige el cliente, agrega los productos o servicios y emite el comprobante."
                />
            </div>
            <form
                onSubmit={submitSale}
                className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    {venta ? (
                        <Card className="flex-row items-start gap-3 rounded-[16px] border-amber-500/30 bg-amber-500/5 p-4 shadow-none">
                            <FileText className="text-warning-strong mt-0.5 size-5 shrink-0" />
                            <div className="text-[12.5px]">
                                <p className="text-foreground font-bold">
                                    {editandoEmitida
                                        ? `Editando ${venta.comprobante ?? venta.numero_interno}`
                                        : editando
                                          ? `Editando el borrador ${venta.numero_interno}`
                                          : `Copia de ${venta.numero_interno}`}
                                </p>
                                <p className="text-muted-foreground">
                                    {editandoEmitida
                                        ? venta.rechazado
                                            ? 'SUNAT lo rechazó: corrige lo que haga falta y al guardar se emite uno nuevo con otro número y la fecha de hoy.'
                                            : 'Cambia cliente, comprobante, productos, precios o pago. Al guardar se vuelve a generar con el mismo número (si pasas de factura a boleta o al revés, toma el número de la otra serie).'
                                        : editando
                                          ? 'Cambia lo que haga falta y emite. La venta conserva su número.'
                                          : 'Ya está todo lleno con los datos de la venta anulada: corrige lo que estaba mal y emite.'}
                                </p>
                            </div>
                        </Card>
                    ) : null}
                    {quote ? (
                        <Card className="border-border bg-card gap-3 rounded-[16px] p-5 shadow-none">
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="bg-destructive/10 text-primary-strong flex size-10 items-center justify-center rounded-[11px]">
                                    <FileText className="size-5" />
                                </div>
                                <div className="min-w-0">
                                    <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                        Desde la cotización {quote.numero}
                                    </h2>
                                    <p className="text-muted-foreground text-[12px]">
                                        Los servicios y productos sin serie ya
                                        se agregaron. Elige o escanea las
                                        unidades de los productos con serie; se
                                        respeta el precio cotizado.
                                    </p>
                                </div>
                            </div>
                            <div className="border-border overflow-hidden rounded-[10px] border">
                                {quote.items.map((line, index) => {
                                    const agregadas = line.serializado
                                        ? form.data.items.filter(
                                              (i) =>
                                                  i.tipo_linea ===
                                                      'unidad_nueva' &&
                                                  i.product_id ===
                                                      line.product_id,
                                          ).length
                                        : null;
                                    const completa =
                                        agregadas === null ||
                                        agregadas >= line.cantidad;

                                    return (
                                        <div
                                            key={`${line.tipo}-${line.product_id ?? line.service_id}-${index}`}
                                            className="border-border bg-card flex items-center justify-between gap-3 border-b px-3 py-2.5 last:border-b-0"
                                        >
                                            <div className="min-w-0">
                                                <span className="text-foreground block truncate text-[13px] font-bold">
                                                    {line.nombre}
                                                </span>
                                                <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                    {line.cantidad} ×{' '}
                                                    {money(
                                                        line.precio_unitario,
                                                    )}
                                                </span>
                                            </div>
                                            <Badge
                                                className={`shrink-0 rounded-full border px-2.5 py-1 text-[10.5px] font-bold ${completa ? 'text-success-strong border-emerald-500/20 bg-emerald-500/10' : 'text-warning-strong border-amber-500/20 bg-amber-500/10'}`}
                                            >
                                                {agregadas === null
                                                    ? 'Agregado'
                                                    : `${agregadas} de ${line.cantidad} unidades`}
                                            </Badge>
                                        </div>
                                    );
                                })}
                            </div>
                            {fieldError(form.errors, 'quote_id')}
                        </Card>
                    ) : null}

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <ClientPicker
                                teamSlug={teamSlug}
                                value={cliente}
                                onChange={elegirCliente}
                                error={form.errors.client_id}
                                autoFocus={!quote}
                                headerExtra={
                                    form.data.comprobante_tipo !== 'factura' &&
                                    !cliente ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                elegirCliente({
                                                    ...clientesVarios,
                                                    vehiculos: [],
                                                    sedes: [],
                                                })
                                            }
                                            className="border-border text-muted-foreground hover:text-foreground rounded-full border px-2.5 py-0.5 text-[11px] font-bold transition-colors"
                                        >
                                            Clientes varios
                                        </button>
                                    ) : null
                                }
                            />

                            <div className="grid content-start gap-3">
                                <div>
                                    <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                        Comprobante
                                    </Label>
                                    <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                        <SegmentButton
                                            disabled={clienteSinRuc}
                                            title={
                                                clienteSinRuc
                                                    ? 'Para factura, el cliente necesita RUC'
                                                    : undefined
                                            }
                                            active={
                                                form.data.comprobante_tipo ===
                                                'factura'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'comprobante_tipo',
                                                    'factura',
                                                )
                                            }
                                        >
                                            Factura
                                        </SegmentButton>
                                        <SegmentButton
                                            active={
                                                form.data.comprobante_tipo ===
                                                'boleta'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'comprobante_tipo',
                                                    'boleta',
                                                )
                                            }
                                        >
                                            Boleta
                                        </SegmentButton>
                                        <SegmentButton
                                            active={
                                                form.data.comprobante_tipo ===
                                                'nota_venta'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'comprobante_tipo',
                                                    'nota_venta',
                                                )
                                            }
                                        >
                                            Nota de venta
                                        </SegmentButton>
                                    </div>
                                    {clienteSinRuc && !esClientesVarios ? (
                                        <p className="text-muted-foreground mt-1 text-[11px]">
                                            Cliente con DNI: se emite boleta.
                                        </p>
                                    ) : null}
                                    {esClientesVarios ? (
                                        <p
                                            className={`mt-1 text-[11px] font-semibold ${boletaSuperaLimite ? 'text-destructive-strong' : 'text-muted-foreground'}`}
                                        >
                                            {boletaSuperaLimite
                                                ? `Supera S/ ${limiteBoletaSinIdentificar.toFixed(2)}: registra el DNI del cliente.`
                                                : `Clientes varios: boleta hasta S/ ${limiteBoletaSinIdentificar.toFixed(2)}.`}
                                        </p>
                                    ) : null}
                                    {fieldError(
                                        form.errors,
                                        'comprobante_tipo',
                                    )}
                                </div>
                                <div className="text-muted-foreground flex items-center gap-2 text-[12px]">
                                    <CalendarClock className="size-3.5" />
                                    Emisión: hoy{' '}
                                    {new Date().toLocaleDateString('es-PE')}
                                </div>
                            </div>
                        </div>
                        {facturaSinDireccion ? (
                            <div className="border-destructive/40 bg-destructive/5 rounded-[12px] border p-3">
                                <div className="text-destructive-strong flex items-center gap-2 text-[12.5px] font-bold">
                                    <MapPin className="size-4 shrink-0" />
                                    Falta la dirección fiscal del cliente
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11.5px]">
                                    La factura la imprime y no se emite sin
                                    ella. Se guarda en la ficha del cliente.
                                </p>
                                <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        value={direccionNueva}
                                        onChange={(event) =>
                                            setDireccionNueva(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Ej. Av. España 1234, Trujillo - La Libertad"
                                        className="h-9 flex-1 rounded-[9px] text-[13px]"
                                    />
                                    <Button
                                        type="button"
                                        disabled={
                                            guardandoDireccion ||
                                            direccionNueva.trim().length < 5
                                        }
                                        onClick={() => void guardarDireccion()}
                                        className="bg-primary hover:bg-primary/90 h-9 rounded-[9px] text-[12.5px] font-bold text-white shadow-none"
                                    >
                                        {guardandoDireccion
                                            ? 'Guardando…'
                                            : 'Guardar dirección'}
                                    </Button>
                                </div>
                                {errorDireccion ? (
                                    <p className="text-destructive-strong mt-1 text-[11px] font-semibold">
                                        {errorDireccion}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </Card>

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="grid gap-4">
                            <div className="border-border rounded-[12px] border p-3">
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Destino de los equipos
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    <SegmentButton
                                        active={
                                            form.data.destino ===
                                            'local_cliente'
                                        }
                                        onClick={() =>
                                            form.setData((data) => ({
                                                ...data,
                                                destino: 'local_cliente',
                                                referencia: '',
                                            }))
                                        }
                                    >
                                        <Building2 className="mr-1 inline size-3" />{' '}
                                        Local
                                    </SegmentButton>
                                    <SegmentButton
                                        active={
                                            form.data.destino === 'vehiculo'
                                        }
                                        onClick={() =>
                                            form.setData((data) => ({
                                                ...data,
                                                destino: 'vehiculo',
                                                referencia: '',
                                            }))
                                        }
                                    >
                                        <Truck className="mr-1 inline size-3" />{' '}
                                        Vehículo
                                    </SegmentButton>
                                </div>
                                <p className="text-muted-foreground mt-1 text-[11px]">
                                    {form.data.destino === 'vehiculo'
                                        ? 'Certificados: Operatividad y Garantía + Prueba Hidrostática.'
                                        : 'Certificados: Operatividad y Garantía + Capacitación.'}
                                </p>
                            </div>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Condición de pago
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    <SegmentButton
                                        active={
                                            form.data.condicion_pago ===
                                            'contado'
                                        }
                                        onClick={() =>
                                            cambiarCondicion('contado')
                                        }
                                    >
                                        <CreditCard className="mr-1 inline size-3" />{' '}
                                        Contado
                                    </SegmentButton>
                                    <SegmentButton
                                        active={
                                            form.data.condicion_pago ===
                                            'credito'
                                        }
                                        onClick={() =>
                                            cambiarCondicion('credito')
                                        }
                                    >
                                        Crédito
                                    </SegmentButton>
                                </div>
                                {form.data.condicion_pago === 'credito' ? (
                                    <button
                                        type="button"
                                        onClick={() => setCreditoAbierto(true)}
                                        className={`mt-1 text-left text-[11.5px] font-semibold underline-offset-2 hover:underline ${creditoIncompleto || cuotasDesactualizadas ? 'text-destructive-strong' : 'text-primary-strong'}`}
                                    >
                                        {creditoIncompleto
                                            ? 'Define las cuotas del crédito'
                                            : cuotasDesactualizadas
                                              ? 'El total cambió: ajusta las cuotas'
                                              : `${form.data.cuotas.length} cuota(s), última el ${new Date(`${form.data.cuotas.at(-1)!.fecha_vencimiento}T00:00:00`).toLocaleDateString('es-PE')} · Editar`}
                                    </button>
                                ) : null}
                                {fieldError(form.errors, 'cuotas')}
                            </div>
                        </div>
                        {form.data.condicion_pago === 'contado' ? (
                            <div className="border-border bg-muted/20 flex flex-col gap-1.5 rounded-[12px] border p-3">
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    ¿Cómo paga el cliente?
                                </Label>
                                <div className="flex flex-wrap gap-1.5">
                                    {MEDIOS_PAGO.map((medio) => (
                                        <button
                                            key={medio.valor}
                                            type="button"
                                            aria-pressed={
                                                form.data.medio_pago ===
                                                medio.valor
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'medio_pago',
                                                    medio.valor,
                                                )
                                            }
                                            className={`rounded-full border px-3 py-1 text-[12px] font-bold transition-colors ${
                                                form.data.medio_pago ===
                                                medio.valor
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border text-muted-foreground hover:border-primary/60 hover:text-primary-strong'
                                            }`}
                                        >
                                            {medio.texto}
                                        </button>
                                    ))}
                                </div>
                                {form.data.medio_pago !== 'efectivo' ? (
                                    <Input
                                        value={form.data.numero_operacion}
                                        onChange={(event) =>
                                            form.setData(
                                                'numero_operacion',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="N.º de operación (opcional)"
                                        maxLength={60}
                                        className="max-w-[260px]"
                                    />
                                ) : null}
                                {caja_abierta === false &&
                                    form.data.medio_pago === 'efectivo' && (
                                        <div className="flex items-start gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-[12px] text-amber-800 dark:text-amber-300">
                                            <AlertCircle className="text-warning-strong mt-0.5 size-4 shrink-0" />
                                            <div>
                                                <p className="font-bold">
                                                    No tienes una caja abierta
                                                </p>
                                                <p className="mt-0.5">
                                                    Para registrar cobros en
                                                    efectivo necesitas abrir tu
                                                    turno de caja primero.{' '}
                                                    <a
                                                        href={`/${teamSlug}/vendedor/caja`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="font-bold underline underline-offset-2 hover:opacity-80"
                                                    >
                                                        Abrir caja aquí ↗
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                {form.data.medio_pago === 'efectivo' &&
                                total >= LIMITE_BANCARIZACION ? (
                                    <p className="text-[11.5px] font-semibold text-amber-700 dark:text-amber-400">
                                        Desde S/ 2,000 el pago debe ir por banco
                                        (transferencia, depósito o tarjeta): en
                                        efectivo el cliente pierde el crédito
                                        fiscal.
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground text-[11px]">
                                        El cobro se registra solo al emitir y
                                        suma en tu caja.
                                    </p>
                                )}
                                {fieldError(form.errors, 'medio_pago')}
                            </div>
                        ) : null}
                        <ReferenciaField
                            cliente={cliente}
                            destino={form.data.destino}
                            value={form.data.referencia}
                            onChange={(valor) =>
                                form.setData('referencia', valor)
                            }
                            error={form.errors.referencia}
                        />
                        {sedeFija ? (
                            <div className="text-muted-foreground flex items-center gap-1.5 text-[12px]">
                                <MapPin className="size-3.5" />
                                Vendes desde{' '}
                                <b className="text-foreground">
                                    {sedeFija.nombre}
                                </b>
                            </div>
                        ) : (
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Sede de la venta
                                </Label>
                                <select
                                    value={form.data.sede_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'sede_id',
                                            event.target.value
                                                ? Number(event.target.value)
                                                : '',
                                        )
                                    }
                                    className="border-border bg-card focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-10 w-full rounded-[9px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="">
                                        Selecciona una sede...
                                    </option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </select>
                                {fieldError(form.errors, 'sede_id')}
                            </div>
                        )}
                    </Card>

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <ScanLine className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Productos y servicios
                                </h2>
                                <p className="text-muted-foreground text-[12px]">
                                    Escanea la serie o el código de barras, o
                                    busca por nombre. Los precios incluyen IGV.
                                </p>
                            </div>
                        </div>

                        <CatalogPicker
                            teamSlug={teamSlug}
                            sedeId={form.data.sede_id}
                            onPick={agregarItem}
                            onPickUnidad={(u) => agregarUnidades([u])}
                            placeholder="Serie BF-EQ, código de barras o nombre del producto o servicio..."
                        />
                        {aviso ? (
                            <div className="text-warning-strong flex items-center gap-2 rounded-[10px] bg-amber-500/10 px-3 py-2 text-[12px] font-semibold">
                                <AlertCircle className="size-4" />
                                {aviso}
                            </div>
                        ) : null}
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
                                                {h || (
                                                    <span className="sr-only">
                                                        Acciones
                                                    </span>
                                                )}
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
                                                    <span className="text-foreground block font-semibold">
                                                        {item.nombre}
                                                    </span>
                                                    <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                        {item.tipo_linea ===
                                                        'unidad_nueva'
                                                            ? `Serie ${item.numero_serie}${item.detalle ? ` · ${item.detalle}` : ''}`
                                                            : item.tipo_linea ===
                                                                'servicio'
                                                              ? 'Servicio'
                                                              : `Producto${item.stock !== undefined && item.stock !== null ? ` · stock ${item.stock}` : ''}`}
                                                    </span>
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    {item.tipo_linea ===
                                                    'unidad_nueva' ? (
                                                        <span className="pl-2">
                                                            1
                                                        </span>
                                                    ) : (
                                                        <div className="flex items-center gap-1">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    actualizarLinea(
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
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    actualizarLinea(
                                                                        item.key,
                                                                        {
                                                                            cantidad:
                                                                                Math.max(
                                                                                    1,
                                                                                    Number(
                                                                                        event
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
                                                                    actualizarLinea(
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
                                                    )}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={
                                                            item.precio_unitario
                                                        }
                                                        onChange={(event) =>
                                                            actualizarLinea(
                                                                item.key,
                                                                {
                                                                    precio_unitario:
                                                                        Number(
                                                                            event
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
                                                        max={
                                                            item.cantidad *
                                                            item.precio_unitario
                                                        }
                                                        step="0.01"
                                                        value={item.descuento}
                                                        onChange={(event) =>
                                                            actualizarLinea(
                                                                item.key,
                                                                {
                                                                    descuento:
                                                                        Math.min(
                                                                            Math.max(
                                                                                0,
                                                                                Number(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                    0,
                                                                            ),
                                                                            item.cantidad *
                                                                                item.precio_unitario,
                                                                        ),
                                                                },
                                                            )
                                                        }
                                                        className="border-border h-8 w-20 rounded-[7px] text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-border border-b px-2.5 py-2.5 font-['IBM_Plex_Mono',monospace] font-bold whitespace-nowrap tabular-nums">
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
                                                                    (l) =>
                                                                        l.key !==
                                                                        item.key,
                                                                ),
                                                            )
                                                        }
                                                        className="border-border bg-card text-destructive-strong size-7 rounded-[7px] shadow-none"
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
                            <div className="bg-destructive/10 text-primary-strong flex size-10 items-center justify-center rounded-[11px]">
                                <FileText className="size-5" />
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
                                Observaciones
                            </Label>
                            <textarea
                                value={form.data.observaciones}
                                onChange={(event) =>
                                    form.setData(
                                        'observaciones',
                                        event.target.value,
                                    )
                                }
                                placeholder="Sale en el comprobante como Obs."
                                className="border-border bg-card focus-visible:border-ring focus-visible:ring-ring/50 mt-1 min-h-[70px] w-full rounded-[9px] border px-3 py-2 text-[13px] outline-none focus-visible:ring-[3px]"
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
                            {editandoEmitida
                                ? 'Guardar cambios'
                                : `Emitir ${NOMBRE_COMPROBANTE[form.data.comprobante_tipo]}`}
                        </Button>
                        {aviso ? (
                            <p
                                className="text-destructive-strong text-[11.5px] font-semibold"
                                role="alert"
                            >
                                {aviso}
                            </p>
                        ) : null}
                        {!aviso &&
                        (boletaSuperaLimite ||
                            creditoIncompleto ||
                            cuotasDesactualizadas) ? (
                            <p
                                className="text-destructive-strong text-[11.5px] font-semibold"
                                role="alert"
                            >
                                {boletaSuperaLimite
                                    ? `Registra el DNI del cliente: la boleta supera S/ ${limiteBoletaSinIdentificar.toFixed(2)}.`
                                    : creditoIncompleto
                                      ? 'Define al menos una cuota antes de emitir.'
                                      : 'Ajusta las cuotas: su suma debe coincidir con el total.'}
                            </p>
                        ) : null}
                        {editandoEmitida ? null : (
                            <Button
                                type="submit"
                                data-accion="borrador"
                                variant="outline"
                                disabled={form.processing}
                                className="border-border bg-card text-foreground h-10 rounded-[9px] shadow-none"
                            >
                                Guardar borrador
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit(
                                    editando && venta?.id
                                        ? ventas.show.url({
                                              current_team: teamSlug,
                                              sale: venta.id,
                                          })
                                        : ventas.index.url(teamSlug),
                                )
                            }
                            className="border-border bg-card text-foreground/80 h-10 rounded-[9px] shadow-none"
                        >
                            Cancelar
                        </Button>
                    </Card>
                </aside>
            </form>

            <UnidadesDialog
                teamSlug={teamSlug}
                sedeId={form.data.sede_id}
                producto={productoConSerie}
                excluir={form.data.items
                    .map((l) => l.numero_serie)
                    .filter((s): s is string => Boolean(s))}
                onClose={() => setProductoConSerie(null)}
                onAgregar={agregarUnidades}
            />

            <CreditoDialog
                open={creditoAbierto}
                onOpenChange={setCreditoAbierto}
                onCancel={() => {
                    if (form.data.cuotas.length === 0) {
                        form.setData('condicion_pago', 'contado');
                    }
                }}
                total={total}
                fecha={form.data.fecha}
                cuotas={form.data.cuotas}
                onSave={(cuotas) => form.setData('cuotas', cuotas)}
            />
        </VendedorLayout>
    );
}
