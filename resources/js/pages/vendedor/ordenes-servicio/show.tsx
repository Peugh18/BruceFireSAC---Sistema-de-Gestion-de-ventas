import DeficiencyController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyController';
import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import ChatFlotante, { marcarLeido } from '@/components/chat-flotante';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import DeficienciaSuelta from '@/components/deficiencia-suelta';
import {
    DeficienciaCard,
    type DeficiencyItem,
    useAccionesDeficiencia,
} from '@/components/deficiencias-vendedor';
import EquiposDeLaOrden, {
    type Casilleros,
} from '@/components/equipos-de-la-orden';
import { NOMBRES_PASOS, type PasoTaller } from '@/components/pasos-taller';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NativeSelect } from '@/components/ui/native-select';
import VendedorLayout from '@/layouts/vendedor-layout';
import CertificadosRoutes from '@/routes/vendedor/certificados';
import comunicacion from '@/routes/vendedor/comunicacion';
import CotizacionesRoutes from '@/routes/vendedor/cotizaciones';
import ordenes from '@/routes/vendedor/ordenes-servicio';
import EntregaMostradorRoutes from '@/routes/vendedor/ordenes-servicio/entrega-mostrador';
import EquiposRoutes from '@/routes/vendedor/ordenes-servicio/equipos';
import VentasRoutes from '@/routes/vendedor/ventas';
import type { Team } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Download, MoreHorizontal } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export type ServiceOrderDetails = {
    id: number;
    codigo: string;
    service?: { nombre: string } | null;
    service_id?: number | null;
    fecha: string;
    prioridad: string;
    departamento_tecnico?: string | null;
    estado: string;
    observaciones?: string | null;
    created_at: string;
    client?: {
        id: number;
        razon_social: string;
        numero_documento?: string;
        telefono?: string;
        direccion_fiscal?: string;
    };
    tecnico?: {
        id: number;
        name: string;
        email?: string;
    } | null;
    sale_id?: number | null;
    sale?: { id: number; numero_interno: string; estado: string } | null;
    quote?: { id: number; numero: string; estado: string } | null;
    certificates?: { id: number; numero: string; estado: string }[];
    equipments: Array<{
        id: number;
        numero_serie: string;
        capacidad?: string;
        tipo_agente?: string;
        marca?: string;
    }>;
};

export type Props = {
    serviceOrder: ServiceOrderDetails;
    casilleros: Casilleros;
    paso: PasoTaller;
    conversacion: ConversacionProps;
    tecnicos: { id: number; name: string }[];
    tecnicosEntrega: { id: number; name: string }[];
    deficiencias: DeficiencyItem[];
};

/** Cada estado técnico dicho como lo entiende la vendedora. */
const ESTADOS: Record<string, string> = {
    pendiente_recepcion: 'Por recibir en taller',
    recibido_planta: 'Recibido en taller',
    en_revision: 'En revisión',
    esperando_autorizacion: 'Espera tu autorización',
    autorizado: 'Autorizado',
    en_proceso: 'En trabajo',
    trabajo_terminado: 'Trabajo terminado',
    pendiente_datos: 'Faltan datos del certificado',
    datos_completos: 'Datos completos',
    listo_certificado: 'Certificado listo',
    listo_entrega: 'Listo para entregar',
    entregado: 'Entregado',
    cerrado: 'Cerrado',
    anulada: 'Anulada',
};

/** Hasta aquí se puede anular: con el certificado emitido ya no. */
const ANULABLES = [
    'pendiente_recepcion',
    'recibido_planta',
    'en_revision',
    'esperando_autorizacion',
    'autorizado',
    'en_proceso',
    'trabajo_terminado',
    'pendiente_datos',
    'datos_completos',
];

const PRIORIDADES: Record<string, string> = {
    normal: 'Normal',
    alta: 'Alta',
    urgente: 'Urgente',
};

const LISTOS = ['listo_certificado', 'listo_entrega', 'entregado', 'cerrado'];

function soloFecha(valor: string): string {
    return valor ? valor.slice(0, 10) : '';
}

function fechaLegible(valor: string): string {
    const fecha = soloFecha(valor);

    return fecha
        ? new Date(`${fecha}T00:00:00`).toLocaleDateString('es-PE', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          })
        : '—';
}

type Tramo = { nombre: string; estado: 'hecho' | 'actual' | 'pendiente' };

/**
 * Los pasos del taller (los mismos que ve planta) más la entrega, con lo que
 * falta ahora y quién lo hace.
 */
function avanceDe(
    orden: ServiceOrderDetails,
    paso: PasoTaller,
): { tramos: Tramo[]; ahora: string; quien: string } {
    const entregada = ['entregado', 'cerrado'].includes(orden.estado);
    const enEntrega = orden.estado === 'listo_entrega';
    const actual = entregada ? 7 : enEntrega ? 6 : paso.numero;
    const tramos = [...NOMBRES_PASOS, 'Entrega'].map((nombre, i) => ({
        nombre,
        estado:
            i + 1 < actual
                ? ('hecho' as const)
                : i + 1 === actual
                  ? ('actual' as const)
                  : ('pendiente' as const),
    }));
    const tecnico =
        orden.tecnico?.name ??
        (orden.departamento_tecnico === 'campo'
            ? 'el técnico de campo'
            : 'el técnico de planta');

    if (entregada) {
        return { tramos, ahora: 'Orden entregada al cliente.', quien: '' };
    }

    if (enEntrega) {
        return {
            tramos,
            ahora: 'Coordina la entrega con el cliente.',
            quien: 'Tú o el técnico de campo',
        };
    }

    return {
        tramos,
        ahora: `${NOMBRES_PASOS[paso.numero - 1]}: ${paso.detalle}.`,
        quien:
            paso.clave === 'hallazgos'
                ? 'Tú: registra la respuesta del cliente'
                : `Lo hace ${tecnico}`,
    };
}

const chip =
    'bg-muted text-foreground inline-flex min-h-11 items-center rounded-full px-2.5 text-xs font-semibold lg:min-h-7 lg:text-[11px]';

export default function ServiceOrderShow({
    serviceOrder,
    casilleros,
    paso,
    conversacion,
    tecnicos,
    tecnicosEntrega,
    deficiencias,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const acciones = useAccionesDeficiencia(teamSlug);
    const avance = avanceDe(serviceOrder, paso);
    const anulada = serviceOrder.estado === 'anulada';
    const editable = !['entregado', 'cerrado', 'anulada'].includes(
        serviceOrder.estado,
    );
    const enEspera = deficiencias.filter(
        (d) => d.estado === 'esperando_autorizacion',
    );
    const otrosHallazgos = deficiencias.filter(
        (d) => d.estado !== 'esperando_autorizacion',
    );
    const [anulando, setAnulando] = useState(false);
    const [motivoAnulacion, setMotivoAnulacion] = useState('');
    const [errorAnulacion, setErrorAnulacion] = useState<string | null>(null);

    // En escritorio el chat está a la vista: abrir la orden lo deja leído.
    useEffect(() => {
        if (
            (conversacion.no_leidos ?? 0) > 0 &&
            window.matchMedia('(min-width: 1024px)').matches
        ) {
            marcarLeido(teamSlug, serviceOrder.id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serviceOrder.id]);

    const anularOrden = () => {
        router.post(
            ordenes.anular.url({
                current_team: teamSlug,
                service_order: serviceOrder.id,
            }),
            { motivo: motivoAnulacion },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAnulando(false);
                    setMotivoAnulacion('');
                },
                onError: (errores) =>
                    setErrorAnulacion(errores.motivo ?? 'No se pudo anular.'),
            },
        );
    };
    const [tecnicoElegido, setTecnicoElegido] = useState('');
    const entrega = useForm({ tecnico_id: '' });
    const ruta = { current_team: teamSlug, service_order: serviceOrder.id };

    function asignar(tecnicoId: string) {
        if (!tecnicoId) {
            return;
        }

        setTecnicoElegido(tecnicoId);
        router.post(
            ordenes.asignarTecnico.url(ruta),
            { tecnico_id: tecnicoId },
            {
                preserveScroll: true,
                onSuccess: () => setTecnicoElegido(''),
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo asignar el técnico.',
                    ),
            },
        );
    }

    /** Cambia un dato de la orden desde la cabecera, sin abrir un formulario. */
    function cambiarDato(
        campo: 'fecha' | 'prioridad' | 'departamento_tecnico',
        valor: string,
    ) {
        router.put(
            ordenes.update.url(ruta),
            {
                service_id: serviceOrder.service_id,
                fecha: soloFecha(serviceOrder.fecha),
                prioridad: serviceOrder.prioridad || 'normal',
                departamento_tecnico:
                    serviceOrder.departamento_tecnico || 'planta',
                tecnico_id:
                    campo === 'departamento_tecnico'
                        ? ''
                        : (serviceOrder.tecnico?.id ?? ''),
                observaciones: serviceOrder.observaciones ?? '',
                [campo]: valor,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? 'No se pudo guardar.',
                    ),
            },
        );
    }

    const dato =
        'border-input bg-card text-foreground h-9 rounded-lg border px-2 text-xs';

    const puedeRegistrarHallazgo = ![
        'anulada',
        'cerrado',
        'entregado',
        'listo_certificado',
        'listo_entrega',
    ].includes(serviceOrder.estado);

    return (
        <VendedorLayout title={`Orden ${serviceOrder.codigo}`}>
            <Head title={`Orden ${serviceOrder.codigo}`} />

            <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                <aside
                    aria-label="Chat con el taller"
                    className="sticky top-0 hidden h-[calc(100dvh-7rem)] min-h-0 min-w-0 flex-col gap-2 lg:col-start-2 lg:row-span-3 lg:row-start-1 lg:flex"
                >
                    <div className="flex items-center justify-between gap-2 px-1">
                        <h2 className="text-foreground text-base font-bold">
                            Chat con el taller
                        </h2>
                        <span className="text-muted-foreground text-xs">
                            Ventas, planta y campo ven lo mismo
                        </span>
                    </div>
                    <div className="min-h-0 flex-1">
                        <ConversacionOrden
                            ordenId={serviceOrder.id}
                            conversacion={conversacion}
                            nota={serviceOrder.observaciones}
                            panel
                        />
                    </div>
                </aside>
                <header className="flex flex-wrap items-center gap-3 lg:col-start-1">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="border-border bg-card size-11 shrink-0 rounded-xl"
                    >
                        <Link
                            href={ServiceOrderController.index.url(teamSlug)}
                            aria-label="Volver a órdenes de servicio"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-[1_1_260px]">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-foreground font-['Oswald',sans-serif] text-[26px] leading-tight font-semibold">
                                {serviceOrder.codigo}
                            </h1>
                            <Badge className="border-border bg-muted text-foreground border">
                                {ESTADOS[serviceOrder.estado] ??
                                    serviceOrder.estado.replace(/_/g, ' ')}
                            </Badge>
                        </div>
                        <p className="text-foreground truncate text-sm font-semibold">
                            {serviceOrder.client?.razon_social ?? 'Sin cliente'}
                            <span className="text-muted-foreground font-normal">
                                {[
                                    serviceOrder.client?.numero_documento &&
                                        ` · RUC/DNI ${serviceOrder.client.numero_documento}`,
                                    serviceOrder.client?.telefono &&
                                        ` · ${serviceOrder.client.telefono}`,
                                ]
                                    .filter(Boolean)
                                    .join('')}
                            </span>
                        </p>
                        <div className="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                            {editable ? (
                                <input
                                    type="date"
                                    aria-label="Fecha programada"
                                    value={soloFecha(serviceOrder.fecha)}
                                    onChange={(e) =>
                                        e.target.value &&
                                        cambiarDato('fecha', e.target.value)
                                    }
                                    className={dato}
                                />
                            ) : (
                                <span className="capitalize">
                                    {fechaLegible(serviceOrder.fecha)}
                                </span>
                            )}
                            {editable ? (
                                <NativeSelect
                                    aria-label="Prioridad"
                                    value={serviceOrder.prioridad || 'normal'}
                                    onChange={(e) =>
                                        cambiarDato('prioridad', e.target.value)
                                    }
                                    className={dato}
                                >
                                    {Object.entries(PRIORIDADES).map(
                                        ([valor, texto]) => (
                                            <option key={valor} value={valor}>
                                                Prioridad {texto.toLowerCase()}
                                            </option>
                                        ),
                                    )}
                                </NativeSelect>
                            ) : (
                                <span>
                                    Prioridad{' '}
                                    {PRIORIDADES[serviceOrder.prioridad] ??
                                        serviceOrder.prioridad}
                                </span>
                            )}
                            {editable &&
                            serviceOrder.estado === 'pendiente_recepcion' ? (
                                <NativeSelect
                                    aria-label="Área"
                                    value={
                                        serviceOrder.departamento_tecnico ||
                                        'planta'
                                    }
                                    onChange={(e) =>
                                        cambiarDato(
                                            'departamento_tecnico',
                                            e.target.value,
                                        )
                                    }
                                    className={dato}
                                >
                                    <option value="planta">
                                        Planta (taller)
                                    </option>
                                    <option value="campo">
                                        Campo (local del cliente)
                                    </option>
                                </NativeSelect>
                            ) : (
                                <span>
                                    {serviceOrder.departamento_tecnico ===
                                    'campo'
                                        ? 'Campo'
                                        : 'Planta'}
                                </span>
                            )}
                            <span>
                                · creada el{' '}
                                {new Date(
                                    serviceOrder.created_at,
                                ).toLocaleDateString('es-PE')}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline" className="h-11">
                            <a
                                href={ordenes.constanciaRecepcion.url({
                                    current_team: teamSlug,
                                    service_order: serviceOrder.id,
                                })}
                            >
                                Constancia PDF
                            </a>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Más acciones"
                                    className="size-11"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={comunicacion.index.url(
                                            { current_team: teamSlug },
                                            {
                                                query: {
                                                    orden: serviceOrder.id,
                                                },
                                            },
                                        )}
                                    >
                                        Ver en Seguimiento de taller
                                    </Link>
                                </DropdownMenuItem>
                                {LISTOS.includes(serviceOrder.estado) ? (
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={EntregaMostradorRoutes.show.url(
                                                {
                                                    current_team: teamSlug,
                                                    service_order:
                                                        serviceOrder.id,
                                                },
                                            )}
                                        >
                                            Entrega en mostrador
                                        </Link>
                                    </DropdownMenuItem>
                                ) : null}
                                {ANULABLES.includes(serviceOrder.estado) ? (
                                    <DropdownMenuItem
                                        className="text-destructive-strong"
                                        onSelect={() => {
                                            setErrorAnulacion(null);
                                            setAnulando(true);
                                        }}
                                    >
                                        Anular orden
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        {serviceOrder.sale_id ? (
                            <Button asChild variant="outline" className="h-11">
                                <Link
                                    href={VentasRoutes.show.url({
                                        current_team: teamSlug,
                                        sale: serviceOrder.sale_id,
                                    })}
                                >
                                    Ver venta
                                </Link>
                            </Button>
                        ) : anulada ? null : (
                            <Button asChild className="h-11 px-5">
                                <Link
                                    href={VentasRoutes.create.url(
                                        { current_team: teamSlug },
                                        {
                                            query: {
                                                orden_servicio: serviceOrder.id,
                                            },
                                        },
                                    )}
                                >
                                    Cobrar
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <section
                    aria-label="Avance de la orden"
                    className="bg-card border-border space-y-2 rounded-2xl border p-3 sm:p-4 lg:col-start-1"
                >
                    {anulada ? (
                        <p className="text-destructive-strong text-sm font-semibold">
                            Orden anulada. Revisa el motivo en el chat.
                        </p>
                    ) : (
                        <>
                            <ol className="grid grid-cols-6 gap-1.5">
                                {avance.tramos.map((tramo) => (
                                    <li
                                        key={tramo.nombre}
                                        aria-current={
                                            tramo.estado === 'actual'
                                                ? 'step'
                                                : undefined
                                        }
                                        className="flex flex-col gap-1"
                                    >
                                        <span
                                            className={`h-1.5 rounded-full ${
                                                tramo.estado === 'hecho'
                                                    ? 'bg-success-strong'
                                                    : tramo.estado === 'actual'
                                                      ? 'bg-primary'
                                                      : 'bg-muted'
                                            }`}
                                        />
                                        <span
                                            className={`flex items-center gap-1 truncate text-xs font-semibold ${
                                                tramo.estado === 'hecho'
                                                    ? 'text-success-strong'
                                                    : tramo.estado === 'actual'
                                                      ? 'text-destructive-strong'
                                                      : 'text-muted-foreground'
                                            }`}
                                        >
                                            {tramo.estado === 'hecho' ? (
                                                <Check
                                                    className="size-3.5 shrink-0"
                                                    aria-label="hecho"
                                                />
                                            ) : null}
                                            <span className="hidden sm:inline">
                                                {tramo.nombre}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                            {(() => {
                                const indice = avance.tramos.findIndex(
                                    (t) => t.estado === 'actual',
                                );

                                return indice >= 0 ? (
                                    <p className="text-destructive-strong text-xs font-bold sm:hidden">
                                        Paso {indice + 1} de 6 ·{' '}
                                        {avance.tramos[indice].nombre}
                                    </p>
                                ) : null;
                            })()}
                            <div className="border-border flex flex-wrap items-center gap-x-5 gap-y-2 border-t pt-2">
                                <p className="text-foreground min-w-0 flex-[1_1_280px] text-sm">
                                    <strong>Ahora:</strong> {avance.ahora}{' '}
                                    {avance.quien ? (
                                        <span className="text-muted-foreground">
                                            {avance.quien}
                                        </span>
                                    ) : null}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {serviceOrder.quote ? (
                                        <a
                                            className={`${chip} hover:bg-muted/70`}
                                            href={CotizacionesRoutes.pdf.url({
                                                current_team: teamSlug,
                                                quote: serviceOrder.quote.id,
                                            })}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Cotización{' '}
                                            {serviceOrder.quote.numero} · PDF
                                        </a>
                                    ) : !serviceOrder.sale &&
                                      serviceOrder.estado !== 'anulada' ? (
                                        <Link
                                            className={`${chip} hover:bg-muted/70`}
                                            href={CotizacionesRoutes.create.url(
                                                { current_team: teamSlug },
                                                {
                                                    query: {
                                                        service_order_id:
                                                            serviceOrder.id,
                                                    },
                                                },
                                            )}
                                        >
                                            Cotizar este servicio
                                        </Link>
                                    ) : null}
                                    {serviceOrder.sale ? (
                                        <Link
                                            className={`${chip} hover:bg-muted/70`}
                                            href={VentasRoutes.show.url({
                                                current_team: teamSlug,
                                                sale: serviceOrder.sale.id,
                                            })}
                                        >
                                            Venta{' '}
                                            {serviceOrder.sale.numero_interno}
                                        </Link>
                                    ) : (
                                        <span
                                            className={`${chip} text-warning-strong bg-amber-500/15`}
                                        >
                                            Venta: falta cobrar
                                        </span>
                                    )}
                                    {serviceOrder.certificates?.length ? (
                                        serviceOrder.certificates.map(
                                            (certificado) => (
                                                <a
                                                    key={certificado.id}
                                                    className={`${chip} text-success-strong gap-1.5 bg-emerald-500/15 hover:bg-emerald-500/25`}
                                                    href={CertificadosRoutes.pdf.url(
                                                        {
                                                            current_team:
                                                                teamSlug,
                                                            certificate:
                                                                certificado.id,
                                                        },
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Download className="size-3.5" />
                                                    Certificado{' '}
                                                    {certificado.numero} · PDF
                                                </a>
                                            ),
                                        )
                                    ) : (
                                        <span
                                            className={`${chip} text-muted-foreground`}
                                        >
                                            Certificados: salen cuando el taller
                                            cierre la orden
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div
                                className={`flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl px-3 py-2 ${
                                    serviceOrder.tecnico
                                        ? 'bg-muted/50'
                                        : 'border border-amber-500/40 bg-amber-500/10'
                                }`}
                            >
                                <p className="text-foreground min-w-0 flex-[1_1_220px] text-sm">
                                    <span className="text-muted-foreground font-semibold">
                                        Técnico de{' '}
                                        {serviceOrder.departamento_tecnico ===
                                        'campo'
                                            ? 'campo'
                                            : 'planta'}
                                        :
                                    </span>{' '}
                                    <strong
                                        className={
                                            serviceOrder.tecnico
                                                ? ''
                                                : 'text-warning-strong'
                                        }
                                    >
                                        {serviceOrder.tecnico?.name ??
                                            'Falta asignar'}
                                    </strong>
                                </p>
                                {editable && tecnicos.length > 0 ? (
                                    <NativeSelect
                                        aria-label={
                                            serviceOrder.tecnico
                                                ? 'Cambiar de técnico'
                                                : 'Asignar técnico'
                                        }
                                        value={tecnicoElegido}
                                        onChange={(event) =>
                                            asignar(event.target.value)
                                        }
                                        className="h-11 w-full text-sm sm:w-64"
                                    >
                                        <option value="">
                                            {serviceOrder.tecnico
                                                ? 'Cambiar de técnico…'
                                                : 'Elegir técnico…'}
                                        </option>
                                        {tecnicos
                                            .filter(
                                                (tecnico) =>
                                                    tecnico.id !==
                                                    serviceOrder.tecnico?.id,
                                            )
                                            .map((tecnico) => (
                                                <option
                                                    key={tecnico.id}
                                                    value={tecnico.id}
                                                >
                                                    {tecnico.name}
                                                </option>
                                            ))}
                                    </NativeSelect>
                                ) : null}
                                {editable && tecnicos.length === 0 ? (
                                    <p className="text-warning-strong text-xs">
                                        No hay técnicos de esta área en la sede.
                                        Pídele al Gerente que asigne uno en
                                        Usuarios.
                                    </p>
                                ) : null}
                            </div>
                        </>
                    )}
                </section>

                <div className="flex flex-wrap items-start gap-4 lg:col-start-1">
                    <div className="flex min-w-0 flex-[999_1_520px] flex-col gap-4">
                        {enEspera.length > 0 && (
                            <section
                                aria-label="Hallazgos que esperan al cliente"
                                className="space-y-3 rounded-2xl border-2 border-amber-500 bg-amber-500/10 p-4"
                            >
                                <h2 className="text-foreground text-base font-bold">
                                    Te toca:{' '}
                                    {enEspera.length === 1
                                        ? '1 hallazgo espera la respuesta del cliente'
                                        : `${enEspera.length} hallazgos esperan la respuesta del cliente`}
                                </h2>
                                {enEspera.map((item) => (
                                    <DeficienciaCard
                                        key={item.id}
                                        item={item}
                                        teamSlug={teamSlug}
                                        acciones={acciones}
                                        enOrden
                                    />
                                ))}
                            </section>
                        )}

                        {(otrosHallazgos.length > 0 ||
                            puedeRegistrarHallazgo) && (
                            <section
                                aria-label="Hallazgos de la orden"
                                className="bg-card border-border space-y-2 rounded-2xl border p-3 sm:p-4 lg:col-start-1"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="text-foreground text-base font-bold">
                                        Hallazgos
                                        {otrosHallazgos.length > 0
                                            ? ` · ${otrosHallazgos.length}`
                                            : ''}
                                    </h2>
                                    {puedeRegistrarHallazgo ? (
                                        <DeficienciaSuelta
                                            ordenId={serviceOrder.id}
                                            equipos={serviceOrder.equipments.map(
                                                (item) => ({
                                                    id: item.id,
                                                    serie: item.numero_serie,
                                                }),
                                            )}
                                            destino={DeficiencyController.store.url(
                                                {
                                                    current_team: teamSlug,
                                                    service_order:
                                                        serviceOrder.id,
                                                },
                                            )}
                                        />
                                    ) : null}
                                </div>
                                {otrosHallazgos.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Sin hallazgos por ahora.
                                    </p>
                                ) : (
                                    otrosHallazgos.map((item) => (
                                        <DeficienciaCard
                                            key={item.id}
                                            item={item}
                                            teamSlug={teamSlug}
                                            acciones={acciones}
                                            enOrden
                                        />
                                    ))
                                )}
                            </section>
                        )}

                        <section
                            aria-label="Extintores de la orden"
                            className="bg-card border-border space-y-2 rounded-2xl border p-3 sm:p-4 lg:col-start-1"
                        >
                            <h2 className="text-foreground text-base font-bold">
                                Extintores
                            </h2>
                            <EquiposDeLaOrden
                                casilleros={casilleros}
                                rutas={{
                                    agregar: EquiposRoutes.store.url(ruta),
                                    reordenar:
                                        EquiposRoutes.reordenar.url(ruta),
                                    editar: (equipment) =>
                                        EquiposRoutes.update.url({
                                            ...ruta,
                                            equipment,
                                        }),
                                    quitar: (equipment) =>
                                        EquiposRoutes.destroy.url({
                                            ...ruta,
                                            equipment,
                                        }),
                                }}
                            />
                        </section>

                        {['listo_certificado', 'listo_entrega'].includes(
                            serviceOrder.estado,
                        ) && (
                            <section
                                aria-label="Entrega de la orden"
                                className="bg-card border-border rounded-2xl border p-4 sm:p-5"
                            >
                                <form
                                    className="space-y-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        entrega.post(
                                            ordenes.asignarEntrega.url(ruta),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    entrega.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <label
                                        htmlFor="entrega-tecnico"
                                        className="text-foreground block text-sm font-semibold"
                                    >
                                        Responsable de la entrega en Campo
                                    </label>
                                    <p className="text-muted-foreground text-xs">
                                        El técnico elegido podrá registrar la
                                        entrega. Se conserva el área y el
                                        historial del trabajo realizado.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <NativeSelect
                                            id="entrega-tecnico"
                                            value={entrega.data.tecnico_id}
                                            required
                                            disabled={
                                                tecnicosEntrega.length === 0
                                            }
                                            onChange={(event) =>
                                                entrega.setData(
                                                    'tecnico_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-11 min-w-0 flex-1 text-sm"
                                        >
                                            <option value="">
                                                Selecciona un técnico de Campo…
                                            </option>
                                            {tecnicosEntrega.map((tecnico) => (
                                                <option
                                                    key={tecnico.id}
                                                    value={tecnico.id}
                                                >
                                                    {tecnico.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <Button
                                            type="submit"
                                            disabled={!entrega.data.tecnico_id}
                                            loading={entrega.processing}
                                            className="h-11"
                                        >
                                            Asignar entrega a Campo
                                        </Button>
                                    </div>
                                    {entrega.errors.tecnico_id && (
                                        <p
                                            role="alert"
                                            className="text-destructive-strong text-xs"
                                        >
                                            {entrega.errors.tecnico_id}
                                        </p>
                                    )}
                                    {tecnicosEntrega.length === 0 && (
                                        <p className="text-muted-foreground text-xs">
                                            No hay técnicos de Campo para esta
                                            sede. Puedes registrar la entrega en
                                            mostrador o pedir al Gerente que
                                            asigne uno.
                                        </p>
                                    )}
                                </form>
                            </section>
                        )}
                    </div>
                </div>
            </div>
            <div className="lg:hidden">
                <ChatFlotante
                    ordenId={serviceOrder.id}
                    codigo={serviceOrder.codigo}
                    conversacion={conversacion}
                    nota={serviceOrder.observaciones}
                />
            </div>
            <Dialog open={anulando} onOpenChange={setAnulando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Anular la orden {serviceOrder.codigo}
                        </DialogTitle>
                    </DialogHeader>
                    <p className="text-muted-foreground text-xs">
                        Úsalo cuando el cliente se arrepiente. Los extintores
                        que estén en el taller se le devuelven sin servicio. Si
                        la orden ya se cobró, primero anula la venta.
                    </p>
                    <textarea
                        aria-label="Motivo de anulación"
                        value={motivoAnulacion}
                        onChange={(e) => setMotivoAnulacion(e.target.value)}
                        placeholder="Motivo de la anulación"
                        className="border-border bg-card min-h-[80px] w-full rounded-lg border p-2 text-sm"
                    />
                    {errorAnulacion ? (
                        <p
                            className="text-destructive-strong text-xs font-semibold"
                            role="alert"
                        >
                            {errorAnulacion}
                        </p>
                    ) : null}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAnulando(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            onClick={anularOrden}
                            className="bg-destructive hover:bg-destructive/90 text-white"
                        >
                            Anular orden
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
            {acciones.dialogos}
        </VendedorLayout>
    );
}
