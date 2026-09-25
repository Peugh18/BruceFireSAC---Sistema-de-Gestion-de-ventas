import { router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardList,
    FileText,
    Loader2,
    Package,
    Plus,
    Search,
    Trash2,
    UserRoundPlus,
    Wrench,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import ClientCreateDialog from '@/components/client-create-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';
import cotizaciones from '@/routes/vendedor/cotizaciones';
import type { Team } from '@/types';

type ClientOption = {
    id: number;
    tipo_documento?: string;
    razon_social: string;
    numero_documento: string;
};

type CatalogOption = {
    id: number;
    nombre: string;
    precio_venta: number | string;
};

type CatalogSearchResult = CatalogOption & { tipo: 'product' | 'service' };

type QuoteItemForm = {
    tipo: 'product' | 'service';
    product_id?: number;
    service_id?: number;
    nombre: string;
    cantidad: number;
    precio_unitario: number;
    descuento: number;
};

type QuoteFormData = {
    client_id: number | '';
    fecha: string;
    vigencia_hasta: string;
    condicion_pago_propuesta: string;
    observaciones: string;
    items: QuoteItemForm[];
};

type Props = {
    clients: ClientOption[];
    products: CatalogOption[];
    services: CatalogOption[];
};

function today() {
    return new Date().toISOString().slice(0, 10);
}

function in15Days() {
    const date = new Date();
    date.setDate(date.getDate() + 15);
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
        <p className="mt-1 text-[11px] font-semibold text-destructive">
            {errors[key]}
        </p>
    ) : null;
}

export default function NuevaCotizacion({
    clients,
    products,
    services,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const form = useForm<QuoteFormData>({
        client_id: '',
        fecha: today(),
        vigencia_hasta: in15Days(),
        condicion_pago_propuesta: '',
        observaciones: '',
        items: [],
    });

    const [clientSearch, setClientSearch] = useState('');
    const [clientDialogOpen, setClientDialogOpen] = useState(false);
    const [searchedClients, setSearchedClients] = useState<
        ClientOption[] | null
    >(null);
    const [selectedClientData, setSelectedClientData] =
        useState<ClientOption | null>(null);
    const [itemSearch, setItemSearch] = useState('');
    const [searchedItems, setSearchedItems] = useState<
        CatalogSearchResult[] | null
    >(null);

    // Sin búsqueda: se muestra el puñado reciente que ya viene en props.
    // Con 2+ caracteres se busca en el servidor contra toda la tabla
    // (miles de clientes/productos reales, no solo el puñado inicial).
    const filteredClients = searchedClients ?? clients;
    const clientNotFound =
        searchedClients !== null && searchedClients.length === 0;

    useEffect(() => {
        const term = clientSearch.trim();
        if (term.length < 2) {
            setSearchedClients(null);
            return;
        }

        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    clientes.search.url(teamSlug, { query: { search: term } }),
                );
                setSearchedClients((await response.json()) as ClientOption[]);
            } catch {
                setSearchedClients([]);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [clientSearch, teamSlug]);

    const defaultCatalogItems: CatalogSearchResult[] = [
        ...products.map((p) => ({ ...p, tipo: 'product' as const })),
        ...services.map((s) => ({ ...s, tipo: 'service' as const })),
    ];
    const catalogItems = searchedItems ?? defaultCatalogItems;

    useEffect(() => {
        const term = itemSearch.trim();
        if (term.length < 2) {
            setSearchedItems(null);
            return;
        }

        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    cotizaciones.buscarCatalogo.url(teamSlug, {
                        query: { search: term },
                    }),
                );
                setSearchedItems(
                    (await response.json()) as CatalogSearchResult[],
                );
            } catch {
                setSearchedItems([]);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [itemSearch, teamSlug]);

    const selectedClient =
        selectedClientData ??
        clients.find((client) => client.id === form.data.client_id);

    const setItems = (items: QuoteItemForm[]) => form.setData('items', items);

    const addItem = (item: CatalogOption & { tipo: 'product' | 'service' }) => {
        setItems([
            ...form.data.items,
            {
                tipo: item.tipo,
                product_id: item.tipo === 'product' ? item.id : undefined,
                service_id: item.tipo === 'service' ? item.id : undefined,
                nombre: item.nombre,
                cantidad: 1,
                precio_unitario: Number(item.precio_venta) || 0,
                descuento: 0,
            },
        ]);
        setItemSearch('');
    };

    const subtotal = form.data.items.reduce(
        (sum, item) =>
            sum + item.cantidad * item.precio_unitario - item.descuento,
        0,
    );
    const igv = subtotal * 0.18;
    const total = subtotal + igv;

    const submitQuote = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(cotizaciones.store.url(teamSlug), { preserveScroll: true });
    };

    const cancel = () => router.visit(cotizaciones.index.url(teamSlug));

    return (
        <VendedorLayout title="Nueva cotización">
            <form
                onSubmit={submitQuote}
                className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="gap-4 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-destructive/10 text-primary">
                                <Search className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                    Cliente y vigencia
                                </h2>
                                <p className="text-[12px] text-muted-foreground">
                                    Selecciona un cliente existente antes de
                                    emitir.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Buscar cliente
                                </Label>
                                <div className="mt-1 flex items-stretch gap-2">
                                    <div className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3">
                                        <Search className="size-3.5 shrink-0 text-muted-foreground" />
                                        <input
                                            value={clientSearch}
                                            onChange={(event) =>
                                                setClientSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="RUC, DNI, nombre o razón social..."
                                            className="h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                                        />
                                    </div>
                                    {clientNotFound ? (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setClientDialogOpen(true)
                                            }
                                            className="h-auto shrink-0 rounded-[9px] bg-primary px-3.5 text-[12.5px] font-bold text-white shadow-none hover:bg-primary/90"
                                        >
                                            <UserRoundPlus className="size-3.5" />
                                            Agregar cliente
                                        </Button>
                                    ) : null}
                                </div>
                                {fieldError(form.errors, 'client_id')}

                                <div className="mt-2 max-h-[168px] overflow-y-auto rounded-[10px] border border-border">
                                    {filteredClients.length === 0 ? (
                                        <div className="px-3 py-4 text-[12px] text-muted-foreground">
                                            {searchedClients !== null
                                                ? 'No está registrado en la base de datos. Usa "Agregar cliente" para registrarlo.'
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
                                                className={`flex w-full items-center justify-between gap-3 border-b border-border px-3 py-2.5 text-left last:border-b-0 ${form.data.client_id === client.id ? 'bg-destructive/10' : 'bg-card hover:bg-muted/40'}`}
                                            >
                                                <span>
                                                    <span className="block text-[13px] font-bold text-foreground">
                                                        {client.razon_social}
                                                    </span>
                                                    <span className="font-['IBM_Plex_Mono',monospace] text-[11px] text-muted-foreground">
                                                        {
                                                            client.numero_documento
                                                        }
                                                    </span>
                                                </span>
                                                {form.data.client_id ===
                                                client.id ? (
                                                    <CheckCircle2 className="size-4 text-primary" />
                                                ) : null}
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-3">
                                <div>
                                    <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                        className="mt-1 h-10 rounded-[9px] border-border bg-card text-[13px]"
                                    />
                                    {fieldError(form.errors, 'fecha')}
                                </div>
                                <div>
                                    <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                        Vigencia hasta
                                    </Label>
                                    <Input
                                        type="date"
                                        value={form.data.vigencia_hasta}
                                        onChange={(event) =>
                                            form.setData(
                                                'vigencia_hasta',
                                                event.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 rounded-[9px] border-border bg-card text-[13px]"
                                    />
                                    {fieldError(form.errors, 'vigencia_hasta')}
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="gap-4 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                <Package className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                    Productos y servicios
                                </h2>
                                <p className="text-[12px] text-muted-foreground">
                                    Busca en el catálogo y agrega a la
                                    cotización.
                                </p>
                            </div>
                        </div>

                        <div className="relative">
                            <div className="flex items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3">
                                <Search className="size-3.5 shrink-0 text-muted-foreground" />
                                <input
                                    value={itemSearch}
                                    onChange={(event) =>
                                        setItemSearch(event.target.value)
                                    }
                                    placeholder="Buscar producto o servicio..."
                                    className="h-11 min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                                />
                            </div>
                            <div className="mt-1.5 max-h-[200px] overflow-y-auto rounded-[10px] border border-border">
                                {catalogItems.length === 0 ? (
                                    <div className="px-3 py-3 text-[12px] text-muted-foreground">
                                        {searchedItems !== null
                                            ? 'Sin coincidencias en el catálogo.'
                                            : 'Escribe para buscar productos o servicios.'}
                                    </div>
                                ) : (
                                    catalogItems.map((item) => (
                                        <button
                                            key={`${item.tipo}-${item.id}`}
                                            type="button"
                                            onClick={() => addItem(item)}
                                            className="flex w-full items-center justify-between gap-3 border-b border-border px-3 py-2.5 text-left last:border-b-0 hover:bg-muted/40"
                                        >
                                            <span className="flex items-center gap-2">
                                                {item.tipo === 'product' ? (
                                                    <Package className="size-3.5 text-muted-foreground" />
                                                ) : (
                                                    <Wrench className="size-3.5 text-muted-foreground" />
                                                )}
                                                <span className="text-[13px] font-bold text-foreground">
                                                    {item.nombre}
                                                </span>
                                            </span>
                                            <span className="font-['IBM_Plex_Mono',monospace] text-[12px] font-bold text-foreground/80">
                                                {money(
                                                    Number(item.precio_venta),
                                                )}
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                        {fieldError(form.errors, 'items')}

                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        {[
                                            'Item',
                                            'Cant.',
                                            'P. Unit.',
                                            'Desc.',
                                            'Subtotal',
                                            '',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                className="border-b border-border px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-muted-foreground uppercase"
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
                                                className="px-2.5 py-10 text-center text-muted-foreground"
                                            >
                                                Agrega un producto o servicio
                                                para empezar.
                                            </td>
                                        </tr>
                                    ) : (
                                        form.data.items.map((item, index) => (
                                            <tr key={`${item.nombre}-${index}`}>
                                                <td className="border-b border-border px-2.5 py-[13px] font-semibold text-foreground">
                                                    {item.nombre}
                                                </td>
                                                <td className="border-b border-border px-2.5 py-[13px]">
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        value={item.cantidad}
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
                                                                                  cantidad:
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
                                                        className="h-8 w-16 rounded-[7px] border-border text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-b border-border px-2.5 py-[13px]">
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
                                                        className="h-8 w-24 rounded-[7px] border-border text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-b border-border px-2.5 py-[13px]">
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
                                                        className="h-8 w-24 rounded-[7px] border-border text-[12px]"
                                                    />
                                                </td>
                                                <td className="border-b border-border px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                                    {money(
                                                        item.cantidad *
                                                            item.precio_unitario -
                                                            item.descuento,
                                                    )}
                                                </td>
                                                <td className="border-b border-border px-2.5 py-[13px]">
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
                                                        className="size-7 rounded-[7px] border-border bg-card text-destructive shadow-none"
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
                    <Card className="gap-4 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-destructive/10 text-primary">
                                <ClipboardList className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                    Resumen
                                </h2>
                                <p className="text-[12px] text-muted-foreground">
                                    {selectedClient?.razon_social ??
                                        'Cliente pendiente'}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-2 rounded-[12px] bg-muted/40 p-4 text-[13px]">
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
                            <div className="border-t border-border pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-bold text-foreground">
                                        Total
                                    </span>
                                    <span className="font-['Oswald',sans-serif] text-[28px] font-semibold text-foreground">
                                        {money(total)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Condición de pago propuesta
                            </Label>
                            <Input
                                value={form.data.condicion_pago_propuesta}
                                onChange={(event) =>
                                    form.setData(
                                        'condicion_pago_propuesta',
                                        event.target.value,
                                    )
                                }
                                placeholder="Ej. Contado, Crédito 30 días..."
                                className="mt-1 h-10 rounded-[9px] border-border bg-card text-[13px]"
                            />
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                className="mt-1 min-h-[86px] w-full rounded-[9px] border border-border bg-card px-3 py-2 text-[13px] outline-none"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="h-11 rounded-[9px] bg-primary px-4 text-[13px] font-bold text-white shadow-none hover:bg-primary/90"
                        >
                            {form.processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <FileText className="size-4" />
                            )}
                            Guardar cotización
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cancel}
                            className="h-10 rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                        >
                            Cancelar
                        </Button>
                    </Card>
                </aside>
            </form>

            <ClientCreateDialog
                open={clientDialogOpen}
                onOpenChange={setClientDialogOpen}
                teamSlug={teamSlug}
                initialDocumento={
                    /^\d{8}$|^\d{11}$/.test(clientSearch.trim())
                        ? clientSearch.trim()
                        : ''
                }
                onCreated={(client) => {
                    form.setData('client_id', client.id);
                    setSelectedClientData(client);
                    setSearchedClients([client]);
                }}
            />
        </VendedorLayout>
    );
}
