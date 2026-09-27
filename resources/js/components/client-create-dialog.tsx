import { useHttp } from '@inertiajs/react';
import { UserRoundPlus } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import { Cargando } from '@/components/cargando';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rucLookup } from '@/routes/vendedor';
import clientes from '@/routes/vendedor/clientes';

export type ClientFormData = {
    tipo_documento: 'ruc' | 'dni';
    numero_documento: string;
    razon_social: string;
    nombre_comercial: string;
    telefono: string;
    whatsapp: string;
    email: string;
    direccion_fiscal: string;
    estado_contribuyente: string;
    condicion_domicilio: string;
    activo: boolean;
    observaciones: string;
};

export type CreatedClient = {
    id: number;
    tipo_documento: string;
    razon_social: string;
    numero_documento: string;
};

const emptyClientForm: ClientFormData = {
    tipo_documento: 'ruc',
    numero_documento: '',
    razon_social: '',
    nombre_comercial: '',
    telefono: '',
    whatsapp: '',
    email: '',
    direccion_fiscal: '',
    estado_contribuyente: '',
    condicion_domicilio: '',
    activo: true,
    observaciones: '',
};

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p className="text-destructive mt-1 text-[11px] font-semibold">
            {message}
        </p>
    );
}

function ClientFormFields({
    data,
    errors,
    setData,
    lookupLoading,
    lookupMessage,
}: {
    data: ClientFormData;
    errors: Partial<Record<keyof ClientFormData, string>>;
    setData: <K extends keyof ClientFormData>(
        key: K,
        value: ClientFormData[K],
    ) => void;
    lookupLoading: boolean;
    lookupMessage: string | null;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    Tipo doc.
                </Label>
                <select
                    value={data.tipo_documento}
                    onChange={(event) =>
                        setData(
                            'tipo_documento',
                            event.target.value as 'ruc' | 'dni',
                        )
                    }
                    className="border-border bg-card mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none"
                >
                    <option value="ruc">RUC</option>
                    <option value="dni">DNI</option>
                </select>
                <FieldError message={errors.tipo_documento} />
            </div>

            <div>
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    Numero
                </Label>
                <div className="relative">
                    <Input
                        value={data.numero_documento}
                        onChange={(event) =>
                            setData(
                                'numero_documento',
                                event.target.value.replace(/\D/g, ''),
                            )
                        }
                        maxLength={data.tipo_documento === 'dni' ? 8 : 11}
                        placeholder={
                            data.tipo_documento === 'dni'
                                ? '8 dígitos'
                                : '11 dígitos'
                        }
                        className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                    />
                    {lookupLoading && (
                        <Cargando className="text-muted-foreground absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2" />
                    )}
                </div>
                <FieldError message={errors.numero_documento} />
                {lookupMessage && !lookupLoading && (
                    <p className="mt-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                        {lookupMessage}
                    </p>
                )}
            </div>

            <div className="sm:col-span-2">
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    {data.tipo_documento === 'dni'
                        ? 'Nombres y apellidos'
                        : 'Razon social'}
                </Label>
                <Input
                    value={data.razon_social}
                    onChange={(event) =>
                        setData('razon_social', event.target.value)
                    }
                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                />
                <FieldError message={errors.razon_social} />
            </div>

            {data.tipo_documento === 'ruc' && (
                <div>
                    <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                        Nombre comercial{' '}
                        <span className="text-muted-foreground font-normal normal-case">
                            (opcional, no viene de SUNAT — se escribe a mano si
                            aplica)
                        </span>
                    </Label>
                    <Input
                        value={data.nombre_comercial}
                        onChange={(event) =>
                            setData('nombre_comercial', event.target.value)
                        }
                        className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                    />
                    <FieldError message={errors.nombre_comercial} />
                </div>
            )}

            <div>
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    Email
                </Label>
                <Input
                    type="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                />
                <FieldError message={errors.email} />
            </div>

            <div>
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    Telefono
                </Label>
                <Input
                    value={data.telefono}
                    onChange={(event) =>
                        setData('telefono', event.target.value)
                    }
                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                />
                <FieldError message={errors.telefono} />
            </div>

            <div>
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    WhatsApp
                </Label>
                <Input
                    value={data.whatsapp}
                    onChange={(event) =>
                        setData('whatsapp', event.target.value)
                    }
                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                />
                <FieldError message={errors.whatsapp} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                    Direccion fiscal
                </Label>
                <Input
                    value={data.direccion_fiscal}
                    onChange={(event) =>
                        setData('direccion_fiscal', event.target.value)
                    }
                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                />
                <FieldError message={errors.direccion_fiscal} />
            </div>

            {data.tipo_documento === 'ruc' && (
                <>
                    <div>
                        <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                            Estado SUNAT{' '}
                            <span className="text-muted-foreground font-normal normal-case">
                                (según consulta)
                            </span>
                        </Label>
                        <Input
                            value={data.estado_contribuyente}
                            readOnly
                            disabled
                            className="border-border bg-muted/40 mt-1 h-9 cursor-not-allowed rounded-[8px] text-[13px]"
                        />
                        <FieldError message={errors.estado_contribuyente} />
                    </div>

                    <div>
                        <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                            Condicion{' '}
                            <span className="text-muted-foreground font-normal normal-case">
                                (según consulta)
                            </span>
                        </Label>
                        <Input
                            value={data.condicion_domicilio}
                            readOnly
                            disabled
                            className="border-border bg-muted/40 mt-1 h-9 cursor-not-allowed rounded-[8px] text-[13px]"
                        />
                        <FieldError message={errors.condicion_domicilio} />
                    </div>
                </>
            )}
        </div>
    );
}

/**
 * Modal "Agregar cliente" compartido por Clientes, Nueva Cotización y Nueva
 * Venta. Autocompleta desde RENIEC/SUNAT y devuelve el cliente creado sin
 * salir de la página, para que quien lo abre pueda seleccionarlo al toque.
 */
export default function ClientCreateDialog({
    open,
    onOpenChange,
    teamSlug,
    initialDocumento = '',
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    teamSlug: string;
    initialDocumento?: string;
    onCreated: (client: CreatedClient) => void;
}) {
    const form = useHttp<ClientFormData, CreatedClient>(emptyClientForm);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupMessage, setLookupMessage] = useState<string | null>(null);
    const [duplicateClient, setDuplicateClient] =
        useState<CreatedClient | null>(null);

    // Al abrir desde un buscador con un RUC/DNI que no existe en la BD, el
    // número llega precargado y la consulta RENIEC/SUNAT se dispara sola.
    useEffect(() => {
        if (!open) {
            return;
        }

        const documento = initialDocumento.replace(/\D/g, '');
        form.setData({
            ...emptyClientForm,
            tipo_documento: documento.length === 8 ? 'dni' : 'ruc',
            numero_documento: documento,
        });
        form.clearErrors();
        setDuplicateClient(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initialDocumento]);

    // Autocompleta Razon social / Direccion / Estado SUNAT desde RENIEC-SUNAT
    // (via APIsPeru) apenas el numero de documento tiene el largo correcto.
    useEffect(() => {
        const numero = form.data.numero_documento.trim();
        const expectedLength = form.data.tipo_documento === 'dni' ? 8 : 11;
        setLookupMessage(null);
        setDuplicateClient(null);

        if (!open || numero.length !== expectedLength) {
            return;
        }

        const timeout = window.setTimeout(async () => {
            setLookupLoading(true);
            try {
                const localResponse = await fetch(
                    clientes.search.url(teamSlug, {
                        query: { search: numero },
                    }),
                );
                const localClients =
                    (await localResponse.json()) as CreatedClient[];
                const existingClient = localClients.find(
                    (client) => client.numero_documento === numero,
                );

                if (existingClient) {
                    setDuplicateClient(existingClient);
                    return;
                }

                const response = await fetch(
                    rucLookup.url(teamSlug, {
                        query: { numero_documento: numero },
                    }),
                );
                const payload = await response.json();

                if (!response.ok) {
                    setLookupMessage(
                        payload.message ?? 'No se pudo consultar el documento.',
                    );
                    return;
                }

                form.setData((previous) => ({
                    ...previous,
                    razon_social: payload.razon_social ?? '',
                    direccion_fiscal:
                        payload.direccion ?? previous.direccion_fiscal,
                    ...(previous.tipo_documento === 'ruc'
                        ? {
                              estado_contribuyente:
                                  payload.estado_contribuyente ?? '',
                              condicion_domicilio:
                                  payload.condicion_domicilio ?? '',
                          }
                        : {}),
                }));
            } catch {
                setLookupMessage(
                    'No se pudo conectar con el servicio de consulta.',
                );
            } finally {
                setLookupLoading(false);
            }
        }, 400);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, form.data.numero_documento, form.data.tipo_documento, teamSlug]);

    const submitClient = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (duplicateClient) {
            return;
        }

        void form.post(clientes.store.url(teamSlug), {
            onSuccess: (client) => {
                onOpenChange(false);
                onCreated(client);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card max-h-[88vh] overflow-y-auto rounded-[16px] sm:max-w-2xl">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="bg-destructive/10 text-primary flex size-10 items-center justify-center rounded-[11px]">
                            <UserRoundPlus className="size-5" />
                        </div>
                        <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                            Agregar cliente
                        </DialogTitle>
                    </div>
                </DialogHeader>

                <form onSubmit={submitClient} className="space-y-4">
                    <ClientFormFields
                        data={form.data}
                        errors={form.errors}
                        setData={form.setData}
                        lookupLoading={lookupLoading}
                        lookupMessage={lookupMessage}
                    />

                    {duplicateClient && (
                        <div className="rounded-[10px] border border-amber-500/30 bg-amber-500/10 p-3">
                            <p className="text-foreground text-sm font-semibold">
                                Ya está registrado:{' '}
                                {duplicateClient.razon_social}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={() => {
                                        onCreated(duplicateClient);
                                        onOpenChange(false);
                                    }}
                                >
                                    Usar este cliente
                                </Button>
                                <Button asChild type="button" variant="outline">
                                    <a
                                        href={clientes.show.url({
                                            current_team: teamSlug,
                                            client: duplicateClient.id,
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Ver ficha
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="border-border bg-card text-foreground/80 rounded-[9px] shadow-none"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || duplicateClient !== null
                            }
                            className="bg-primary hover:bg-primary/90 rounded-[9px] font-bold text-white shadow-none"
                        >
                            Guardar cliente
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
