import { router } from '@inertiajs/react';
import { CheckCircle2, Search, UserRoundPlus } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import ClientCreateDialog from '@/components/client-create-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';

export type ComprobanteClient = {
    id: number;
    tipo_documento?: string;
    razon_social: string;
    numero_documento: string;
};

type ComprobanteTipo = 'factura' | 'boleta';

/**
 * Corrige un comprobante que aún no se envió a SUNAT (o que fue rechazado):
 * factura ↔ boleta y/o el cliente, sin nota de crédito.
 */
export default function ComprobanteEditDialog({
    open,
    onOpenChange,
    teamSlug,
    saleId,
    total,
    tipoActual,
    clienteActual,
    clientesVarios,
    limiteBoletaSinIdentificar,
    rechazado,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    teamSlug: string;
    saleId: number;
    total: number;
    tipoActual: ComprobanteTipo;
    clienteActual: ComprobanteClient;
    clientesVarios: ComprobanteClient;
    limiteBoletaSinIdentificar: number;
    rechazado: boolean;
}) {
    const [tipo, setTipo] = useState<ComprobanteTipo>(tipoActual);
    const [cliente, setCliente] = useState<ComprobanteClient>(clienteActual);
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<ComprobanteClient[] | null>(
        null,
    );
    const [creandoCliente, setCreandoCliente] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        setTipo(tipoActual);
        setCliente(clienteActual);
        setBusqueda('');
        setResultados(null);
        setError(null);
    }, [open, tipoActual, clienteActual]);

    useEffect(() => {
        const term = busqueda.trim();

        if (term.length < 2) {
            setResultados(null);
            return;
        }

        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    clientes.search.url(teamSlug, { query: { search: term } }),
                );
                setResultados((await response.json()) as ComprobanteClient[]);
            } catch {
                setResultados([]);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [busqueda, teamSlug]);

    const clienteSinRuc =
        cliente.tipo_documento !== undefined &&
        cliente.tipo_documento !== 'ruc';
    const esClientesVarios = cliente.id === clientesVarios.id;
    const superaLimite =
        tipo === 'boleta' &&
        esClientesVarios &&
        total > limiteBoletaSinIdentificar;
    const noEncontrado = resultados !== null && resultados.length === 0;

    useEffect(() => {
        if (clienteSinRuc && tipo === 'factura') {
            setTipo('boleta');
        }
    }, [clienteSinRuc, tipo]);

    const guardar = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setGuardando(true);
        setError(null);

        router.put(
            ventas.corregirComprobante.url({
                current_team: teamSlug,
                sale: saleId,
            }),
            { comprobante_tipo: tipo, client_id: cliente.id },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'No se pudo corregir el comprobante.',
                    ),
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[88vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                            {rechazado
                                ? 'Corregir y reemitir'
                                : 'Editar comprobante'}
                        </DialogTitle>
                        <DialogDescription className="text-[12.5px]">
                            {rechazado
                                ? 'SUNAT rechazó el comprobante: se emite uno nuevo con otro número y la fecha de hoy.'
                                : 'Aún no se envió a SUNAT, así que se corrige sin nota de crédito.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4">
                        <div>
                            <div className="text-[11px] font-bold text-foreground/80 uppercase">
                                Tipo de comprobante
                            </div>
                            <div className="mt-1 flex rounded-[9px] bg-muted p-[3px]">
                                {(['factura', 'boleta'] as const).map(
                                    (opcion) => (
                                        <button
                                            key={opcion}
                                            type="button"
                                            disabled={
                                                opcion === 'factura' &&
                                                clienteSinRuc
                                            }
                                            onClick={() => setTipo(opcion)}
                                            className={`flex-1 rounded-[7px] px-3.5 py-1.5 text-xs font-bold capitalize transition-all disabled:cursor-not-allowed disabled:opacity-40 ${tipo === opcion ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {opcion}
                                        </button>
                                    ),
                                )}
                            </div>
                            {clienteSinRuc ? (
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    Para factura, el cliente necesita RUC.
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <div className="flex items-center justify-between gap-2">
                                <div className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Cliente
                                </div>
                                {tipo === 'boleta' ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setCliente(clientesVarios)
                                        }
                                        className={`rounded-full border px-2.5 py-0.5 text-[11px] font-bold transition-colors ${esClientesVarios ? 'border-primary bg-destructive/10 text-primary' : 'border-border text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Clientes varios
                                    </button>
                                ) : null}
                            </div>

                            <div className="mt-1 flex items-center justify-between gap-3 rounded-[10px] border border-primary/40 bg-destructive/5 px-3 py-2.5">
                                <span>
                                    <span className="block text-[13px] font-bold text-foreground">
                                        {cliente.razon_social}
                                    </span>
                                    <span className="font-['IBM_Plex_Mono',monospace] text-[11px] text-muted-foreground">
                                        {cliente.numero_documento}
                                    </span>
                                </span>
                                <CheckCircle2 className="size-4 shrink-0 text-primary" />
                            </div>
                            {superaLimite ? (
                                <p className="mt-1 text-[11px] font-semibold text-destructive">
                                    La boleta a CLIENTES VARIOS no puede superar
                                    S/ {limiteBoletaSinIdentificar.toFixed(2)}.
                                </p>
                            ) : null}

                            <div className="mt-2 flex items-stretch gap-2">
                                <div className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3">
                                    <Search className="size-3.5 shrink-0 text-muted-foreground" />
                                    <input
                                        value={busqueda}
                                        onChange={(event) =>
                                            setBusqueda(event.target.value)
                                        }
                                        placeholder="Cambiar cliente: RUC, DNI o nombre..."
                                        className="h-9 min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                                    />
                                </div>
                                {noEncontrado ? (
                                    <Button
                                        type="button"
                                        onClick={() => setCreandoCliente(true)}
                                        className="h-auto shrink-0 rounded-[9px] bg-primary px-3 text-[12px] font-bold text-white shadow-none hover:bg-primary/90"
                                    >
                                        <UserRoundPlus className="size-3.5" />
                                        Agregar cliente
                                    </Button>
                                ) : null}
                            </div>

                            {resultados && resultados.length > 0 ? (
                                <div className="mt-2 max-h-[168px] overflow-y-auto rounded-[10px] border border-border">
                                    {resultados.map((opcion) => (
                                        <button
                                            key={opcion.id}
                                            type="button"
                                            onClick={() => {
                                                setCliente(opcion);
                                                setBusqueda('');
                                            }}
                                            className="flex w-full flex-col border-b border-border bg-card px-3 py-2 text-left last:border-b-0 hover:bg-muted/40"
                                        >
                                            <span className="text-[13px] font-bold text-foreground">
                                                {opcion.razon_social}
                                            </span>
                                            <span className="font-['IBM_Plex_Mono',monospace] text-[11px] text-muted-foreground">
                                                {opcion.numero_documento}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            ) : noEncontrado ? (
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    No está registrado. Usa "Agregar cliente".
                                </p>
                            ) : null}
                        </div>

                        {error ? (
                            <p className="rounded-[9px] bg-destructive/10 px-3 py-2 text-[12px] font-semibold text-destructive">
                                {error}
                            </p>
                        ) : null}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                                className="rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardando || superaLimite}
                                className="rounded-[9px] bg-primary font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                {guardando
                                    ? 'Guardando…'
                                    : rechazado
                                      ? 'Reemitir'
                                      : 'Guardar cambios'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ClientCreateDialog
                open={creandoCliente}
                onOpenChange={setCreandoCliente}
                teamSlug={teamSlug}
                initialDocumento={
                    /^\d{8}$|^\d{11}$/.test(busqueda.trim())
                        ? busqueda.trim()
                        : ''
                }
                onCreated={(nuevo) => {
                    setCliente(nuevo);
                    setBusqueda('');
                }}
            />
        </>
    );
}
