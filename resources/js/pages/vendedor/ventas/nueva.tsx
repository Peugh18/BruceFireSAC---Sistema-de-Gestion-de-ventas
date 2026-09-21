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
import { FormEvent, KeyboardEvent, useEffect, useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import { rucLookup } from '@/routes/vendedor';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type ClientOption = {
    id: number;
    razon_social: string;
    numero_documento: string;
};

type LineType = 'unidad_nueva' | 'recarga_servicio';
type Destination = 'local_cliente' | 'vehiculo';
type PaymentCondition = 'contado' | 'credito_30';
type DocumentType = 'factura' | 'boleta';

type SaleItemForm = {
    tipo_linea: LineType;
    numero_serie: string;
    catalog_item_id: number;
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

type Props = { clients: ClientOption[] };

type ScanResponse = {
    inventory_unit_id: number;
    numero_serie: string;
    catalog_item_id: number;
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
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(value || 0);
}

function fieldError(errors: Partial<Record<string, string>>, key: string) {
    return errors[key] ? <p className="mt-1 text-[11px] font-semibold text-[#B91C1C]">{errors[key]}</p> : null;
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
                active ? 'bg-white text-[#201F1D] shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#6B6862] hover:text-[#201F1D]'
            }`}
        >
            {children}
        </button>
    );
}

export default function NuevaVenta({ clients }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');

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
    const [lookup, setLookup] = useState<LookupResponse | null>(null);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [tipoLinea, setTipoLinea] = useState<LineType>('unidad_nueva');
    const [scanValue, setScanValue] = useState('');
    const [scanLoading, setScanLoading] = useState(false);
    const [scanMessage, setScanMessage] = useState<string | null>(null);

    const filteredClients = useMemo(() => {
        const term = clientSearch.trim().toLowerCase();
        if (!term) return clients;

        return clients.filter((client) => {
            return client.razon_social.toLowerCase().includes(term) || client.numero_documento.includes(term);
        });
    }, [clientSearch, clients]);

    const selectedClient = clients.find((client) => client.id === form.data.client_id);

    useEffect(() => {
        const value = clientSearch.trim();
        setLookup(null);

        if (!/^\d{8}$|^\d{11}$/.test(value)) {
            return;
        }

        const timeout = window.setTimeout(async () => {
            setLookupLoading(true);
            try {
                const response = await fetch(rucLookup.url(teamSlug, { query: { numero_documento: value } }));
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

    const subtotal = form.data.items.reduce((sum, item) => sum + item.cantidad * item.precio_unitario - item.descuento, 0);
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
            const payload = (await response.json()) as ScanResponse & { message?: string };

            if (!response.ok) {
                setScanMessage(payload.message ?? 'No se pudo resolver la serie.');
                return;
            }

            setItems([
                ...form.data.items,
                {
                    tipo_linea: tipoLinea,
                    numero_serie: payload.numero_serie,
                    catalog_item_id: payload.catalog_item_id,
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
            <form onSubmit={submitSale} className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-[#FBE7E7] text-[#E31E24]">
                                <Search className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase text-[#201F1D]">Cliente y comprobante</h2>
                                <p className="text-[12px] text-[#8A8680]">Selecciona un cliente existente antes de emitir.</p>
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Buscar cliente</Label>
                                <div className="mt-1 flex items-center gap-2 rounded-[9px] border border-[#E4E1DC] bg-[#FAFAF8] px-3">
                                    <Search className="size-3.5 shrink-0 text-[#8A8680]" />
                                    <input
                                        value={clientSearch}
                                        onChange={(event) => setClientSearch(event.target.value)}
                                        placeholder="RUC, DNI o razón social..."
                                        className="h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-[#8A8680]"
                                    />
                                    {lookupLoading ? <Loader2 className="size-3.5 animate-spin text-[#8A8680]" /> : null}
                                </div>
                                {fieldError(form.errors, 'client_id')}

                                <div className="mt-2 max-h-[168px] overflow-y-auto rounded-[10px] border border-[#E7E4DE]">
                                    {filteredClients.length === 0 ? (
                                        <div className="px-3 py-4 text-[12px] text-[#8A8680]">No hay coincidencias en los clientes recientes.</div>
                                    ) : (
                                        filteredClients.map((client) => (
                                            <button
                                                key={client.id}
                                                type="button"
                                                onClick={() => form.setData('client_id', client.id)}
                                                className={`flex w-full items-center justify-between gap-3 border-b border-[#F1EFEC] px-3 py-2.5 text-left last:border-b-0 ${form.data.client_id === client.id ? 'bg-[#FBE7E7]' : 'bg-white hover:bg-[#FAFAF8]'}`}
                                            >
                                                <span>
                                                    <span className="block text-[13px] font-bold text-[#201F1D]">{client.razon_social}</span>
                                                    <span className="font-['IBM_Plex_Mono',monospace] text-[11px] text-[#8A8680]">{client.numero_documento}</span>
                                                </span>
                                                {form.data.client_id === client.id ? <CheckCircle2 className="size-4 text-[#E31E24]" /> : null}
                                            </button>
                                        ))
                                    )}
                                </div>

                                {lookup ? (
                                    <div className="mt-2 rounded-[10px] border border-dashed border-[#D9D5CE] bg-[#F8F7F4] px-3 py-2 text-[11.5px] text-[#6B6862]">
                                        {lookup.message ? lookup.message : `Consulta: ${lookup.razon_social ?? lookup.nombre_o_razon_social ?? 'documento encontrado'}`}
                                    </div>
                                ) : null}
                            </div>

                            <div className="grid gap-3">
                                <div>
                                    <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Fecha</Label>
                                    <Input type="date" value={form.data.fecha} onChange={(event) => form.setData('fecha', event.target.value)} className="mt-1 h-10 rounded-[9px] border-[#E4E1DC] bg-white text-[13px]" />
                                    {fieldError(form.errors, 'fecha')}
                                </div>
                                <div>
                                    <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Tipo comprobante</Label>
                                    <div className="mt-1 flex rounded-[9px] bg-[#F1EFEC] p-[3px]">
                                        <SegmentButton active={form.data.comprobante_tipo === 'factura'} onClick={() => form.setData('comprobante_tipo', 'factura')}>Factura</SegmentButton>
                                        <SegmentButton active={form.data.comprobante_tipo === 'boleta'} onClick={() => form.setData('comprobante_tipo', 'boleta')}>Boleta</SegmentButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="grid gap-4 lg:grid-cols-3">
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Destino</Label>
                                <div className="mt-1 flex rounded-[9px] bg-[#F1EFEC] p-[3px]">
                                    <SegmentButton active={form.data.destino === 'local_cliente'} onClick={() => form.setData('destino', 'local_cliente')}><Building2 className="mr-1 inline size-3" /> Local</SegmentButton>
                                    <SegmentButton active={form.data.destino === 'vehiculo'} onClick={() => form.setData('destino', 'vehiculo')}><Truck className="mr-1 inline size-3" /> Vehículo</SegmentButton>
                                </div>
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Condición de pago</Label>
                                <div className="mt-1 flex rounded-[9px] bg-[#F1EFEC] p-[3px]">
                                    <SegmentButton active={form.data.condicion_pago === 'contado'} onClick={() => form.setData('condicion_pago', 'contado')}><CreditCard className="mr-1 inline size-3" /> Contado</SegmentButton>
                                    <SegmentButton active={form.data.condicion_pago === 'credito_30'} onClick={() => form.setData('condicion_pago', 'credito_30')}>Crédito 30</SegmentButton>
                                </div>
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Sede almacén</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={form.data.sede_id}
                                    onChange={(event) => form.setData('sede_id', event.target.value ? Number(event.target.value) : '')}
                                    placeholder="ID de sede"
                                    className="mt-1 h-10 rounded-[9px] border-[#E4E1DC] bg-white text-[13px]"
                                />
                                {fieldError(form.errors, 'sede_id')}
                            </div>
                        </div>
                    </Card>

                    <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-[#E9EFFD] text-[#2563EB]">
                                <ScanLine className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase text-[#201F1D]">Escaneo de items</h2>
                                <p className="text-[12px] text-[#8A8680]">El tipo seleccionado se aplicará al próximo escaneo.</p>
                            </div>
                            <div className="flex-1" />
                            <div className="flex rounded-[9px] bg-[#F1EFEC] p-[3px]">
                                <SegmentButton active={tipoLinea === 'unidad_nueva'} onClick={() => setTipoLinea('unidad_nueva')}>Extintor nuevo</SegmentButton>
                                <SegmentButton active={tipoLinea === 'recarga_servicio'} onClick={() => setTipoLinea('recarga_servicio')}>Recarga en planta</SegmentButton>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input
                                value={scanValue}
                                onChange={(event) => setScanValue(event.target.value)}
                                onKeyDown={(event: KeyboardEvent<HTMLInputElement>) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        void scanSerial();
                                    }
                                }}
                                placeholder="Escanear o escribir número de serie..."
                                className="h-11 rounded-[9px] border-[#E4E1DC] bg-[#FAFAF8] text-[13px]"
                            />
                            <Button type="button" onClick={() => void scanSerial()} disabled={scanLoading} className="h-11 rounded-[9px] bg-[#18181B] px-4 text-[13px] font-bold text-white shadow-none hover:bg-[#27272A]">
                                {scanLoading ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                                Agregar
                            </Button>
                        </div>
                        {scanMessage ? <div className="flex items-center gap-2 rounded-[10px] bg-[#FDF1E0] px-3 py-2 text-[12px] font-semibold text-[#B45309]"><AlertCircle className="size-4" />{scanMessage}</div> : null}
                        {fieldError(form.errors, 'items')}

                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>{['Item', 'Serie', 'Tipo', 'Cant.', 'P. Unit.', 'Desc.', 'Subtotal', ''].map((h) => <th key={h} className="border-b border-[#E7E4DE] px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-[#8A8680] uppercase">{h}</th>)}</tr>
                                </thead>
                                <tbody>
                                    {form.data.items.length === 0 ? (
                                        <tr><td colSpan={8} className="px-2.5 py-10 text-center text-[#8A8680]">Escanea una unidad para empezar la venta.</td></tr>
                                    ) : (
                                        form.data.items.map((item, index) => (
                                            <tr key={`${item.numero_serie}-${index}`}>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-semibold text-[#201F1D]">{item.nombre}</td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace]">{item.numero_serie}</td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                                    <Badge className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold ${item.tipo_linea === 'unidad_nueva' ? 'bg-[#E5F5EC] text-[#1E8E5A]' : 'bg-[#E9EFFD] text-[#2563EB]'}`}>
                                                        {item.tipo_linea === 'unidad_nueva' ? 'Extintor nuevo' : 'Recarga en planta'}
                                                    </Badge>
                                                </td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{item.cantidad}</td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                                    <Input type="number" min={0} step="0.01" value={item.precio_unitario} onChange={(event) => setItems(form.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, precio_unitario: Number(event.target.value) } : row))} className="h-8 w-24 rounded-[7px] border-[#E4E1DC] text-[12px]" />
                                                </td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                                    <Input type="number" min={0} step="0.01" value={item.descuento} onChange={(event) => setItems(form.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, descuento: Number(event.target.value) } : row))} className="h-8 w-24 rounded-[7px] border-[#E4E1DC] text-[12px]" />
                                                </td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">{money(item.cantidad * item.precio_unitario - item.descuento)}</td>
                                                <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                                    <Button type="button" variant="outline" size="icon" onClick={() => setItems(form.data.items.filter((_, rowIndex) => rowIndex !== index))} className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#B91C1C] shadow-none">
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
                    <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-[#FBE7E7] text-[#E31E24]">
                                <FileText className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase text-[#201F1D]">Panel fiscal</h2>
                                <p className="text-[12px] text-[#8A8680]">{selectedClient?.razon_social ?? 'Cliente pendiente'}</p>
                            </div>
                        </div>

                        <div className="space-y-2 rounded-[12px] bg-[#FAFAF8] p-4 text-[13px]">
                            <div className="flex justify-between"><span className="text-[#6B6862]">Subtotal</span><span className="font-['IBM_Plex_Mono',monospace] font-bold">{money(subtotal)}</span></div>
                            <div className="flex justify-between"><span className="text-[#6B6862]">IGV 18%</span><span className="font-['IBM_Plex_Mono',monospace] font-bold">{money(igv)}</span></div>
                            <div className="border-t border-[#E4E1DC] pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-bold text-[#201F1D]">Total</span>
                                    <span className="font-['Oswald',sans-serif] text-[28px] font-semibold text-[#201F1D]">{money(total)}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Observaciones</Label>
                            <textarea value={form.data.observaciones} onChange={(event) => form.setData('observaciones', event.target.value)} className="mt-1 min-h-[86px] w-full rounded-[9px] border border-[#E4E1DC] bg-white px-3 py-2 text-[13px] outline-none" />
                        </div>

                        <Button type="submit" disabled={form.processing} className="h-11 rounded-[9px] bg-[#E31E24] px-4 text-[13px] font-bold text-white shadow-none hover:bg-[#C9181D]">
                            {form.processing ? <Loader2 className="size-4 animate-spin" /> : <FileText className="size-4" />}
                            Emitir comprobante
                        </Button>
                        <Button type="button" variant="outline" onClick={cancel} className="h-10 rounded-[9px] border-[#E4E1DC] bg-white text-[#4A4742] shadow-none">
                            Cancelar
                        </Button>
                    </Card>

                    <Card className="gap-2 rounded-[16px] border-[#E7E4DE] bg-white p-4 shadow-none">
                        <div className="text-[11px] font-bold tracking-[0.05em] text-[#8A8680] uppercase">Resumen operativo</div>
                        <div className="flex justify-between text-[12.5px]"><span>Items</span><span className="font-bold">{form.data.items.length}</span></div>
                        <div className="flex justify-between text-[12.5px]"><span>Destino</span><span className="font-bold capitalize">{form.data.destino.replaceAll('_', ' ')}</span></div>
                        <div className="flex justify-between text-[12.5px]"><span>Pago</span><span className="font-bold capitalize">{form.data.condicion_pago.replaceAll('_', ' ')}</span></div>
                    </Card>
                </aside>
            </form>
        </VendedorLayout>
    );
}
