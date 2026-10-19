import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Settings2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    ClasificacionDetraccion,
    type EstadoDetraccion,
    type OpcionDetraccion,
} from '@/components/datos-tributarios-catalogo';
import CodigoSunatInput from '@/components/codigo-sunat-input';
import CertificadosRoutes from '@/routes/gerente/configuracion/certificados';
import TiposRoutes from '@/routes/gerente/tipos-de-servicio';

export type TipoDeServicioItem = {
    id: number;
    nombre: string;
    estado_detraccion: EstadoDetraccion;
    codigo_detraccion: string | null;
    detraccion: string | null;
    certificate_type_id: number | null;
    certificado: string | null;
    codigo_sunat: string | null;
    pide_extintor: boolean;
    atencion: string | null;
    activo: boolean;
};

type Opcion = { id: number; nombre: string };
type Detraccion = OpcionDetraccion;

const vacio = {
    nombre: '',
    estado_detraccion: 'pendiente' as EstadoDetraccion,
    codigo_detraccion: '',
    certificate_type_id: '',
    codigo_sunat: '',
    pide_extintor: false,
    atencion: '',
};

/** Código del servicio de extintores (catálogo 25): se sugiere al marcar "Se hace a un extintor". */
const CODIGO_SERVICIO_EXTINTOR = '72101516';

/**
 * Agregar, cambiar o quitar tipos de servicio. Cada tipo define el
 * certificado, la detracción y el código SUNAT de sus servicios.
 */
export function TiposDeServicioModal({
    equipo,
    tipos,
    certificados,
    detracciones,
}: {
    equipo: string;
    tipos: TipoDeServicioItem[];
    certificados: Opcion[];
    detracciones: Detraccion[];
}) {
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<TipoDeServicioItem | null>(null);
    const [datos, setDatos] = useState(vacio);
    const [errores, setErrores] = useState<Record<string, string>>({});

    const editar = (tipo: TipoDeServicioItem | null) => {
        setEditando(tipo);
        setErrores({});
        setDatos(
            tipo
                ? {
                      nombre: tipo.nombre,
                      estado_detraccion: tipo.estado_detraccion ?? 'pendiente',
                      codigo_detraccion: tipo.codigo_detraccion ?? '',
                      certificate_type_id: tipo.certificate_type_id
                          ? String(tipo.certificate_type_id)
                          : '',
                      codigo_sunat: tipo.codigo_sunat ?? '',
                      pide_extintor: tipo.pide_extintor,
                      atencion: tipo.atencion ?? '',
                  }
                : vacio,
        );
    };

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const payload = {
            ...datos,
            codigo_detraccion:
                datos.estado_detraccion === 'sujeto'
                    ? datos.codigo_detraccion || null
                    : null,
            certificate_type_id: datos.certificate_type_id || null,
            codigo_sunat: datos.codigo_sunat || null,
            atencion: datos.atencion || null,
        };
        const opciones = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => editar(null),
            onError: (e: Record<string, string>) => setErrores(e),
        };
        if (editando) {
            router.put(
                TiposRoutes.update.url({
                    current_team: equipo,
                    tipo_de_servicio: editando.id,
                }),
                payload,
                opciones,
            );
        } else {
            router.post(
                TiposRoutes.store.url({ current_team: equipo }),
                payload,
                opciones,
            );
        }
    };

    const quitar = (tipo: TipoDeServicioItem) =>
        router.delete(
            TiposRoutes.destroy.url({
                current_team: equipo,
                tipo_de_servicio: tipo.id,
            }),
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    editar(null);
                    setAbierto(true);
                }}
                className="text-primary-strong inline-flex items-center gap-1 text-xs font-semibold hover:underline"
            >
                <Settings2 className="size-3.5" aria-hidden="true" />
                Gestionar
            </button>
            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tipos de servicio</DialogTitle>
                        <DialogDescription>
                            Cada tipo define el certificado, la detracción y el
                            código SUNAT de sus servicios.
                        </DialogDescription>
                    </DialogHeader>

                    <ul className="divide-border divide-y text-sm">
                        {tipos.map((tipo) => (
                            <li
                                key={tipo.id}
                                className="flex items-start justify-between gap-3 py-2"
                            >
                                <span className="min-w-0">
                                    <span
                                        className={`block font-medium ${tipo.activo ? 'text-foreground' : 'text-muted-foreground line-through'}`}
                                    >
                                        {tipo.nombre}
                                    </span>
                                    <span className="text-muted-foreground block text-xs">
                                        {tipo.certificado ?? 'Sin certificado'}{' '}
                                        ·{' '}
                                        {tipo.estado_detraccion === 'pendiente'
                                            ? 'Detracción por definir'
                                            : tipo.estado_detraccion ===
                                                'no_sujeto'
                                              ? 'No lleva detracción'
                                              : `${tipo.codigo_detraccion} · ${tipo.detraccion ?? 'Sujeto a detracción'}`}
                                    </span>
                                </span>
                                <span className="flex shrink-0 gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Cambiar ${tipo.nombre}`}
                                        onClick={() => editar(tipo)}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                    {tipo.activo && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Quitar ${tipo.nombre}`}
                                            onClick={() => quitar(tipo)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <form
                        onSubmit={guardar}
                        className="border-border space-y-3 rounded-xl border p-3"
                    >
                        {Object.keys(errores).length > 0 && (
                            <p
                                role="alert"
                                className="text-destructive-strong text-sm"
                            >
                                {Object.values(errores)[0]}
                            </p>
                        )}
                        <p className="text-sm font-semibold">
                            {editando
                                ? `Cambiar «${editando.nombre}»`
                                : 'Agregar tipo de servicio'}
                        </p>
                        <div>
                            <label
                                htmlFor="tipo-nombre"
                                className="mb-1 block text-xs font-medium"
                            >
                                Nombre
                            </label>
                            <Input
                                id="tipo-nombre"
                                required
                                maxLength={120}
                                value={datos.nombre}
                                onChange={(e) =>
                                    setDatos({
                                        ...datos,
                                        nombre: e.target.value,
                                    })
                                }
                                placeholder="Ej. Instalación de gabinetes"
                                aria-invalid={!!errores.nombre}
                            />
                            {errores.nombre && (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {errores.nombre}
                                </p>
                            )}
                        </div>
                        <div>
                            <div className="mb-1 flex items-center justify-between gap-2">
                                <label
                                    htmlFor="tipo-certificado"
                                    className="block text-xs font-medium"
                                >
                                    Certificado que emite
                                </label>
                                <Link
                                    href={CertificadosRoutes.create.url({
                                        current_team: equipo,
                                    })}
                                    className="text-primary-strong text-xs font-semibold hover:underline"
                                >
                                    + Crear certificado
                                </Link>
                            </div>
                            <NativeSelect
                                id="tipo-certificado"
                                value={datos.certificate_type_id}
                                onChange={(e) =>
                                    setDatos({
                                        ...datos,
                                        certificate_type_id: e.target.value,
                                    })
                                }
                                className="w-full"
                                aria-invalid={!!errores.certificate_type_id}
                            >
                                <option value="">No emite certificado</option>
                                {certificados.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.nombre}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                        <ClasificacionDetraccion
                            id="tipo-detraccion"
                            estado={datos.estado_detraccion}
                            codigo={datos.codigo_detraccion}
                            opciones={detracciones}
                            errores={errores}
                            onChange={(estado, codigo) =>
                                setDatos({
                                    ...datos,
                                    estado_detraccion: estado,
                                    codigo_detraccion: codigo,
                                })
                            }
                        />
                        <div className="text-xs">
                            <CodigoSunatInput
                                id="tipo-sunat"
                                tipo="servicio"
                                teamSlug={equipo}
                                label="Código SUNAT del servicio"
                                value={datos.codigo_sunat}
                                onChange={(codigo) =>
                                    setDatos((actual) => ({
                                        ...actual,
                                        codigo_sunat: codigo,
                                    }))
                                }
                                error={errores.codigo_sunat}
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={datos.pide_extintor}
                                onChange={(e) =>
                                    setDatos({
                                        ...datos,
                                        pide_extintor: e.target.checked,
                                        codigo_sunat:
                                            e.target.checked &&
                                            !datos.codigo_sunat
                                                ? CODIGO_SERVICIO_EXTINTOR
                                                : datos.codigo_sunat,
                                    })
                                }
                                className="accent-primary size-4"
                            />
                            Se hace a un extintor (pide agente y capacidad)
                        </label>
                        <div className="flex justify-end gap-2">
                            {editando && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => editar(null)}
                                >
                                    Cancelar
                                </Button>
                            )}
                            <Button type="submit">
                                <Plus className="size-4" aria-hidden="true" />
                                {editando ? 'Guardar cambios' : 'Agregar'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
