import type { FirmaDelCertificado, Firmante } from './firmas-del-certificado';

export const bloquesCertificado = {
    datos: 'Datos del cliente y de la atención',
    declaracion: 'Declaración de certificación',
    tabla: 'Tabla de equipos o productos',
    pruebas: 'Pruebas realizadas',
    texto: 'Texto: especificaciones, garantía o mantenimiento',
    resultado: 'Resultado de la evaluación',
    responsabilidad: 'Texto final y responsabilidad',
    diploma: 'Diploma de capacitación',
} as const;

export type BloqueCertificado = {
    tipo: keyof typeof bloquesCertificado;
    titulo?: string | null;
    texto?: string | null;
};

export type ColumnaCertificado = {
    clave?: string;
    titulo: string;
    tipo: 'texto' | 'numero';
    unidad?: string | null;
    obligatorio?: boolean;
};

export type PruebaCertificado = {
    texto: string;
    unidad?: string | null;
    minimo?: number | null;
    maximo?: number | null;
};

export type PlantillaDinamica = {
    id: number;
    nombre: string;
    titulo: string | null;
    subtitulo: string | null;
    norma: string | null;
    declaracion: string | null;
    responsabilidad: string | null;
    prefijo: string;
    vigencia_meses: number;
    orientacion: 'vertical' | 'horizontal';
    familia: string;
    composicion?: BloqueCertificado[] | null;
    bloques?: { titulo: string; texto: string }[] | null;
    columnas: ColumnaCertificado[];
    checklist: PruebaCertificado[];
    firmantes: FirmaDelCertificado[];
    product_ids?: number[];
    tipo_de_servicio_ids?: number[];
    formato_fijo?: boolean;
    vista_previa?: string;
};

export type RelacionCertificado = {
    id: number;
    nombre: string;
    certificate_type_id: number | null;
};

export type EditorDinamicoProps = {
    certificado: PlantillaDinamica | null;
    bases: PlantillaDinamica[];
    maximoColumnas: Record<'vertical' | 'horizontal', number>;
    maximoFirmantes: number;
    firmantesDisponibles: Firmante[];
    productosDisponibles?: RelacionCertificado[];
    tiposDeServicioDisponibles?: RelacionCertificado[];
    productoInicial?: number | null;
};

/** El contenido antiguo se conserva al pasar una plantilla al editor por bloques. */
export function composicionInicial(
    plantilla?: PlantillaDinamica | null,
): BloqueCertificado[] {
    if (plantilla?.composicion) return plantilla.composicion;
    if (plantilla?.familia === 'diploma') return [{ tipo: 'diploma' }];

    return [
        { tipo: 'datos' as const },
        { tipo: 'declaracion' as const },
        { tipo: 'tabla' as const },
        ...(plantilla?.checklist.length ? [{ tipo: 'pruebas' as const }] : []),
        ...(plantilla?.bloques ?? []).map((bloque) => ({
            tipo: 'texto' as const,
            ...bloque,
        })),
        { tipo: 'resultado' as const },
        { tipo: 'responsabilidad' as const },
    ];
}
