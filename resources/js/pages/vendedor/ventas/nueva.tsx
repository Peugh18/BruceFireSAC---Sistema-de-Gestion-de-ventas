import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    CreditCard,
    FileText,
    Loader2,
    Plus,
    ScanLine,
    Search,
    Trash2,
    Truck,
} from 'lucide-react';
import { FormEvent, KeyboardEvent, useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import { rucLookup } from '@/routes/vendedor';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type ClientOption = {
    id: number;
    razon_social: string;
    numero_documento: string;
};

type SedeOption = {
    id: number;
    nombre: string;
};

type LineType = 'unidad_nueva' | 'recarga_servicio';
type Destination = 'local_cliente' | 'vehiculo';
type PaymentCondition = 'contado' | 'credito_30';
type DocumentType = 'factura' | 'boleta' | 'nota_venta';

type SaleItemForm = {
    tipo_linea: LineType;
    numero_serie: string;
    product_id?: number;
    service_id?: number;
    cantidad: number;
    precio_unitario: number;
    descuento: number;
    nombre: string;
    inventory_unit_id?: number;
};

type SaleFormData = {
    client_id: number | '';
    sede_id: number | '';
    vehicle_id: number | '';
    quote_id: number | '';
    fecha: string;
    destino: Destination;
    condicion_pago: PaymentCondition;
    comprobante_tipo: DocumentType;
    observaciones: string;
    items: SaleItemForm[];
};

type Props = { clients: ClientOption[]; sedes: SedeOption[] };

type ScanResponse = {
    inventory_unit_id: number;
    numero_serie: string;
    product_id: number;
    nombre: string;
    precio_venta: number | string;
};

type LookupResponse = {
    razon_social?: string;
    nombre_o_razon_social?: string;
    numero_documento?: string;
    estado_contribuyente?: string;
    condicion_domicilio?: string;
    message?: string;
};

function today() {
    return new Date().toISOString().slice(0, 10);
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

function SegmentButton({
    active,
    children,
    onClick,
}: {
    active: boolean;
    children: React.ReactNode;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                active
                    ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                    : 'text-muted-foreground hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

export default function NuevaVenta({ clients, sedes }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const form = useForm<SaleFormData>({
        client_id: '',
        sede_id: '',
        vehicle_id: '',
        quote_id: '',
        fecha: today(),
        destino: 'local_cliente',
        condicion_pago: 'contado',
        comprobante_tipo: 'factura',
        observaciones: '',
        items: [],
    });

    const [clientSearch, setClientSearch] = useState('');
    const [searchedClients, setSearchedClients] = useState<
        ClientOption[] | null
    >(null);
    const [clientSearchLoading, setClientSearchLoading] = useState(false);
    const [selectedClientData, setSelectedClientData] =
        useState<ClientOption | null>(null);
    const [lookup, setLookup] = useState<LookupResponse | null>(null);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [tipoLinea, setTipoLinea] = useState<LineType>('unidad_nueva');
    const [scanValue, setScanValue] = useState('');
    const [scanLoading, setScanLoading] = useState(false);
    const [scanMessage, setScanMessage] = useState<string | null>(null);

    // Sin búsqueda activa: se muestran los clientes recientes que ya vienen
    // en props. Con 2+ caracteres se busca en el servidor contra toda la
    // tabla de clientes (miles de registros reales, no solo los 10 recientes).
    const filteredClients = searchedClients ?? clients;

    useEffect(() => {
        const term = clientSearch.trim();

        if (term.length < 2) {
            setSearchedClients(null);
            return;
        }

        const timeout = window.setTimeout(async () => {
            setClientSearchLoading(true);
            try {
                const response = await fetch(
                    clientes.search.url(teamSlug, {
                        query: { search: term },
                    }),
                );
                const payload = (await response.json()) as ClientOption[];
                setSearchedClients(payload);
            } catch {
                setSearchedClients([]);
            } finally {
                setClientSearchLoading(false);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [clientSearch, teamSlug]);

    const selectedClient =
        selectedClientData ??
        clients.find((client) => client.id === form.data.client_id);

    useEffect(() => {
        const value = clientSearch.trim();
        setLookup(null);

        if (!/^\d{8}$|^\d{11}$/.test(value)) {
            return;
        }

        const timeout = window.setTimeout(async () => {
            setLookupLoading(true);
            try {
                const response = await fetch(
                    rucLookup.url(teamSlug, {
                        query: { numero_documento: value },
                    }),
                );
                const payload = (await response.json()) as LookupResponse;
                setLookup(payload);
            } catch {
                setLookup({ message: 'No se pudo consultar el documento.' });
            } finally {
                setLookupLoading(false);
            }
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [clientSearch, teamSlug]);

    const subtotal = form.data.items.reduce(
        (sum, item) =>
            sum + item.cantidad * item.precio_unitario - item.descuento,
        0,
    );
    const igv = subtotal * 0.18;
    const total = subtotal + igv;

    const setItems = (items: SaleItemForm[]) => form.setData('items', items);

    const scanSerial = async () => {
        const numeroSerie = scanValue.trim();
        const sedeAlmacenId = form.data.sede_id;
        setScanMessage(null);

        if (!numeroSerie) {
            setScanMessage('Ingresa o escanea un número de serie.');
            return;
        }

        if (!sedeAlmacenId) {
            setScanMessage('Ingresa la sede de almacén antes de escanear.');
            return;
        }

        if (form.data.items.some((item) => item.numero_serie === numeroSerie)) {
            setScanMessage('Esta serie ya está en la venta.');
            return;
        }

        setScanLoading(true);
        try {
            const response = await fetch(
                ventas.escanearSerie.url(teamSlug, {
                    query: {
                        numero_serie: numeroSerie,
                        sede_almacen_id: sedeAlmacenId,
                    },
                }),
            );
            const payload = (await response.json()) as ScanResponse & {
                message?: string;
            };

            if (!response.ok) {
                setScanMessage(
                    payload.message ?? 'No se pudo resolver la serie.',
                );
                return;
            }

            setItems([
                ...form.data.items,
                {
                    tipo_linea: tipoLinea,
                    numero_serie: payload.numero_serie,
                    product_id: payload.product_id,
                    cantidad: 1,
                    precio_unitario: Number(payload.precio_venta) || 0,
                    descuento: 0,
                    nombre: payload.nombre,
                    inventory_unit_id: payload.inventory_unit_id,
                },
            ]);
            setScanValue('');
        } catch {
            setScanMessage('No se pudo conectar con el escáner de series.');
        } finally {
            setScanLoading(false);
        }
    };

    const submitSale = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(ventas.store.url(teamSlug), {
            preserveScroll: true,
        });
    };

    const cancel = () => router.visit(ventas.index.url(teamSlug));

    return (
        <VendedorLayout title="Nueva venta">
            <form
                onSubmit={submitSale}
                className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="bg-destructive/10 text-primary flex size-10 items-center justify-center rounded-[11px]">
                                <Search className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Cliente y comprobante
                                </h2>
                                <p className="text-muted-foreground text-[12px]">
                                    Selecciona un cliente existente antes de
                                    emitir.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Buscar cliente
                                </Label>
                                <div className="border-border bg-muted/40 mt-1 flex items-center gap-2 rounded-[9px] border px-3">
                                    <Search className="text-muted-foreground size-3.5 shrink-0" />
                                    <input
                                        value={clientSearch}
                                        onChange={(event) =>
                                            setClientSearch(event.target.value)
                                        }
                                        placeholder="RUC, DNI o razón social..."
                                        className="placeholder:text-muted-foreground h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                                    />
                                    {lookupLoading || clientSearchLoading ? (
                                        <Loader2 className="text-muted-foreground size-3.5 animate-spin" />
                                    ) : null}
                                </div>
                                {fieldError(form.errors, 'client_id')}

                                <div className="border-border mt-2 max-h-[168px] overflow-y-auto rounded-[10px] border">
                                    {filteredClients.length === 0 ? (
                                        <div className="text-muted-foreground px-3 py-4 text-[12px]">
                                            {searchedClients !== null
                                                ? 'No se encontraron clientes con esa búsqueda.'
                                                : 'No hay clientes recientes. Escribe para buscar.'}
                                        </div>
                                    ) : (
                                        filteredClients.map((client) => (
                                            <button
                                                key={client.id}
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'client_id',
                                                        client.id,
                                                    );
                                                    setSelectedClientData(
                                                        client,
                                                    );
                                                }}
                                                className={`border-border flex w-full items-center justify-between gap-3 border-b px-3 py-2.5 text-left last:border-b-0 ${form.data.client_id === client.id ? 'bg-destructive/10' : 'bg-card hover:bg-muted/40'}`}
                                            >
                                                <span>
                                                    <span className="text-foreground block text-[13px] font-bold">
                                                        {client.razon_social}
                                                    </span>
                                                    <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                        {
                                                            client.numero_documento
                                                        }
                                                    </span>
                                                </span>
                                                {form.data.client_id ===
                                                client.id ? (
                                                    <CheckCircle2 className="text-primary size-4" />
                                                ) : null}
                                            </button>
                                        ))
                                    )}
                                </div>

                                {lookup ? (
                                    <div className="border-border bg-muted/30 text-muted-foreground mt-2 rounded-[10px] border border-dashed px-3 py-2 text-[11.5px]">
                                        {lookup.message
                                            ? lookup.message
                                            : `Consulta: ${lookup.razon_social ?? lookup.nombre_o_razon_social ?? 'documento encontrado'}`}
                                    </div>
                                ) : null}
                            </div>

                            <div className="grid gap-3">
                                <div>
                                    <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                        Fecha
                                    </Label>
                                    <Input
                                        type="date"
                                        value={form.data.fecha}
                                        onChange={(event) =>
                                            form.setData(
                                                'fecha',
                                                event.target.value,
                                            )
                                        }
                                        className="border-border bg-card mt-1 h-10 rounded-[9px] text-[13px]"
                                    />
                                    {fieldError(form.errors, 'fecha')}
                                </div>
                                <div>
                                    <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                        Tipo comprobante
                                    </Label>
                                    <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                        <SegmentButton
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
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="grid gap-4 lg:grid-cols-3">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Destino
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    <SegmentButton
                                        active={
                                            form.data.destino ===
                                            'local_cliente'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'destino',
                                                'local_cliente',
                                            )
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
                                            form.setData('destino', 'vehiculo')
                                        }
                                    >
                                        <Truck className="mr-1 inline size-3" />{' '}
                                        Vehículo
                                    </SegmentButton>
                                </div>
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
                                            form.setData(
                                                'condicion_pago',
                                                'contado',
                                            )
                                        }
                                    >
                                        <CreditCard className="mr-1 inline size-3" />{' '}
                                        Contado
                                    </SegmentButton>
                                    <SegmentButton
                                        active={
                                            form.data.condicion_pago ===
                                            'credito_30'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'condicion_pago',
                                                'credito_30',
                                            )
                                        }
                                    >
                                        Crédito 30
                                    </SegmentButton>
                                </div>
                            </div>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Sede almacén
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
                                    className="border-border bg-card mt-1 h-10 w-full rounded-[9px] border px-3 text-[13px] outline-none"
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
                        </div>
                    </Card>

                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <ScanLine className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Escaneo de items
                                </h2>
                                <p className="text-muted-foreground text-[12px]">
                                    El tipo seleccionado se aplicará al próximo
                                    escaneo.
                                </p>
                            </div>
                            <div className="flex-1" />
                            <div className="bg-muted flex rounded-[9px] p-[3px]">
                                <SegmentButton
                                    active={tipoLinea === 'unidad_nueva'}
                                    onClick={() => setTipoLinea('unidad_nueva')}
                                >
                                    Extintor nuevo
                                </SegmentButton>
                                <SegmentButton
                                    active={tipoLinea === 'recarga_servicio'}
                                    onClick={() =>
                                        setTipoLinea('recarga_servicio')
                                    }
                                >
                                    Recarga en planta
                                </SegmentButton>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input
                                value={scanValue}
                                onChange={(event) =>
                                    setScanValue(event.target.value)
                                }
                                onKeyDown={(
                                    event: KeyboardEvent<HTMLInputElement>,
                                ) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        void scanSerial();
                                    }
                                }}
                                placeholder="Escanear o escribir número de serie..."
                                className="border-border bg-muted/40 h-11 rounded-[9px] text-[13px]"
                            />
                            <Button
                                type="button"
                                onClick={() => void scanSerial()}
                                disabled={scanLoading}
                                className="bg-card hover:bg-foreground/90 h-11 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                            >
                                {scanLoading ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Plus className="size-4" />
                                )}
                                Agregar
                            </Button>
                        </div>
                        {scanMessage ? (
                            <div className="flex items-center gap-2 rounded-[10px] bg-amber-500/10 px-3 py-2 text-[12px] font-semibold text-amber-600 dark:text-amber-400">
                                <AlertCircle className="size-4" />
                                {scanMessage}
                            </div>
                        ) : null}
                        {fieldError(form.errors, 'items')}

                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        {[
                                            'Item',
                                            'Serie',
                                            'Tipo',
                                            'Cant.',
                                            'P. Unit.',
                                            'Desc.',
                                            'Subtotal',
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
                                                colSpan={8}
                                                className="text-muted-foreground px-2.5 py-10 text-center"
                                            >
                                                Escanea una unidad para empezar
                                                la venta.
                                            </td>
                                        </tr>
                                    ) : (
                                        form.data.items.map((item, index) => (
                                            <tr
                                                key={`${item.numero_serie}-${index}`}
                                            >
                                                <td className="border-border text-foreground border-b px-2.5 py-[13px] font-semibold">
                                                    {item.nombre}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace]">
                                                    {item.numero_serie}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <Badge
                                                        className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold ${item.tipo_linea === 'unidad_nueva' ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400'}`}
                                                    >
                                                        {item.tipo_linea ===
                                                        'unidad_nueva'
                                                            ? 'Extintor nuevo'
                                                            : 'Recarga en planta'}
                                                    </Badge>
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    {item.cantidad}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={
                                                            item.precio_unitario
                                                        }
                                                        onChange={(event) =>
                                                            setItems(
                                                                form.data.items.map(
                                                                    (
                                                                        row,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  precio_unitario:
                                                                                      Number(
                                                                                          event
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                              }
                                                                            : row,
                                                                ),
                                                            )
                                                        }
                                                        className="border-border h-8 w-24 rounded-[7px] text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={item.descuento}
                                                        onChange={(event) =>
                                                            setItems(
                                                                form.data.items.map(
                                                                    (
                                                                        row,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  descuento:
                                                                                      Number(
                                                                                          event
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                              }
                                                                            : row,
                                                                ),
                                                            )
                                                        }
                                                        className="border-border h-8 w-24 rounded-[7px] text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                                    {money(
                                                        item.cantidad *
                                                            item.precio_unitario -
                                                            item.descuento,
                                                    )}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        onClick={() =>
                                                            setItems(
                                                                form.data.items.filter(
                                                                    (
                                                                        _,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex !==
                                                                        index,
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
                                <FileText className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Panel fiscal
                                </h2>
                                <p className="text-muted-foreground text-[12px]">
                                    {selectedClient?.razon_social ??
                                        'Cliente pendiente'}
                                </p>
                            </div>
                        </div>

                        <div className="bg-muted/40 space-y-2 rounded-[12px] p-4 text-[13px]">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Subtotal
                                </span>
                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                    {money(subtotal)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    IGV 18%
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
                                className="border-border bg-card mt-1 min-h-[86px] w-full rounded-[9px] border px-3 py-2 text-[13px] outline-none"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary/90 h-11 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            {form.processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <FileText className="size-4" />
                            )}
                            Emitir comprobante
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cancel}
                            className="border-border bg-card text-foreground/80 h-10 rounded-[9px] shadow-none"
                        >
                            Cancelar
                        </Button>
                    </Card>

                    <Card className="border-border bg-card gap-2 rounded-[16px] p-4 shadow-none">
                        <div className="text-muted-foreground text-[11px] font-bold tracking-[0.05em] uppercase">
                            Resumen operativo
                        </div>
                        <div className="flex justify-between text-[12.5px]">
                            <span>Items</span>
                            <span className="font-bold">
                                {form.data.items.length}
                            </span>
                        </div>
                        <div className="flex justify-between text-[12.5px]">
                            <span>Destino</span>
                            <span className="font-bold capitalize">
                                {form.data.destino.replaceAll('_', ' ')}
                            </span>
                        </div>
                        <div className="flex justify-between text-[12.5px]">
                            <span>Pago</span>
                            <span className="font-bold capitalize">
                                {form.data.condicion_pago.replaceAll('_', ' ')}
                            </span>
                        </div>
                    </Card>
                </aside>
            </form>
        </VendedorLayout>
    );
}
