import { createContext, useContext, useEffect, useRef, useState } from 'react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useDraggable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { CSSProperties, ReactNode } from 'react';

import logo from '../../../images/brand/bruce-fire-logo-grafito.png';
import cola from '../../../images/certificados/cola-seccion.png';
import franjaInferior from '../../../images/certificados/franja-inferior-derecha.png';
import franjaSuperior from '../../../images/certificados/franja-superior-izquierda.png';
import marcaAgua from '../../../images/certificados/marca-agua.png';
import sello from '../../../images/certificados/sello-con-texto.png';
import fuenteCond from '../../../fonts/barlow-semicondensed-bold.ttf';
import fuenteTexto from '../../../fonts/barlow-regular.ttf';
import fuenteTextoNegrita from '../../../fonts/barlow-semibold.ttf';
import fuenteMono from '../../../fonts/ibmplexmono-semibold.ttf';
import fuenteTitulo from '../../../fonts/montserrat-black-italic.ttf';
import fuenteSubtitulo from '../../../fonts/montserrat-extrabold-italic.ttf';

export type ZonaCertificado =
    | 'encabezado'
    | 'datos'
    | 'tabla'
    | 'pruebas'
    | 'cierre'
    | 'firmas';

export type HojaDatos = {
    familia?: string;
    diseno?: DisenoCertificado;
    titulo: string;
    subtitulo: string;
    norma: string;
    declaracion: string;
    responsabilidad: string;
    prefijo: string;
    vigenciaMeses: number;
    orientacion: 'vertical' | 'horizontal';
    columnas: { titulo: string; unidad?: string | null }[];
    tieneResultado: boolean;
    pruebas: string[];
    firmantes: {
        nombre: string;
        cargo: string | null;
        cip: string | null;
        firma_url?: string | null;
    }[];
};

export type DisenoCertificado = Partial<
    Record<ZonaCertificado, { x: number; y: number }>
>;
type TextoCertificado =
    | 'titulo'
    | 'subtitulo'
    | 'norma'
    | 'declaracion'
    | 'responsabilidad';
const Edicion = createContext<{ diseno: DisenoCertificado; escala: number }>({
    diseno: {},
    escala: 1,
});

function TextoEditable({
    valor,
    etiqueta,
    onCambiar,
}: {
    valor: string;
    etiqueta: string;
    onCambiar?: (valor: string) => void;
}) {
    return (
        <span
            contentEditable={Boolean(onCambiar)}
            suppressContentEditableWarning
            role={onCambiar ? 'textbox' : undefined}
            aria-label={onCambiar ? etiqueta : undefined}
            tabIndex={onCambiar ? 0 : undefined}
            onBlur={(evento) =>
                onCambiar?.(evento.currentTarget.textContent?.trim() ?? '')
            }
            onKeyDown={(evento) => evento.stopPropagation()}
            className={
                onCambiar
                    ? 'rounded-sm outline-offset-2 focus:outline-2 focus:outline-blue-600'
                    : undefined
            }
        >
            {valor}
        </span>
    );
}

/** Colores del PDF (certificado-operatividad.blade.php): la hoja es impresa, no sigue el tema. */
const C = {
    texto: '#1b1918',
    rojo: '#d20404',
    barra: '#2e2d2c',
    gris: '#6b6965',
    linea: '#e6e6e6',
    fondo: '#f6f5f4',
    verde: '#0e7a3e',
};

/** A4 a 72 ppp: 1 pt del PDF = 1 px aquí. */
const MEDIDA = {
    vertical: { ancho: 595, alto: 842 },
    horizontal: { ancho: 842, alto: 595 },
};

const EJEMPLO_FILAS = 3;

const fuentes = `
@font-face{font-family:CertTitulo;src:url(${fuenteTitulo}) format('truetype')}
@font-face{font-family:CertSub;src:url(${fuenteSubtitulo}) format('truetype')}
@font-face{font-family:CertCond;src:url(${fuenteCond}) format('truetype');font-weight:700}
@font-face{font-family:CertTexto;src:url(${fuenteTexto}) format('truetype')}
@font-face{font-family:CertTexto;src:url(${fuenteTextoNegrita}) format('truetype');font-weight:700}
@font-face{font-family:CertMono;src:url(${fuenteMono}) format('truetype')}`;

/** Tamaño que entra en una línea, como hace el PDF con `tamanoQueEntra`. */
const tamano = (texto: string, maximo: number, caracteres: number) =>
    Math.max(
        9,
        Math.min(maximo, (maximo * caracteres) / Math.max(texto.length, 1)),
    );

function Barra({ children }: { children: ReactNode }) {
    return (
        <div style={{ display: 'flex', alignItems: 'stretch' }}>
            <div
                style={{
                    flex: 1,
                    background: C.barra,
                    borderLeft: `3.7px solid ${C.rojo}`,
                    color: '#fff',
                    fontFamily: 'CertCond',
                    fontWeight: 700,
                    fontSize: 9.8,
                    letterSpacing: 0.5,
                    padding: '2.4px 7px',
                    textTransform: 'uppercase',
                }}
            >
                {children}
            </div>
            <img src={cola} alt="" style={{ width: 15, height: 'auto' }} />
        </div>
    );
}

/** Capa que marca la parte de la hoja que se edita al hacer clic. */
function Zona({
    zona,
    activa,
    etiqueta,
    onElegir,
    children,
    style,
}: {
    zona: ZonaCertificado;
    activa: ZonaCertificado | null;
    etiqueta: string;
    onElegir?: (zona: ZonaCertificado) => void;
    children: ReactNode;
    style?: CSSProperties;
}) {
    const { diseno, escala } = useContext(Edicion);
    const { attributes, listeners, setNodeRef, transform } = useDraggable({
        id: zona,
        disabled: !onElegir,
    });
    const posicion = diseno[zona] ?? { x: 0, y: 0 };
    return (
        <div
            ref={setNodeRef}
            style={{
                position: 'relative',
                ...style,
                left: posicion.x,
                top: posicion.y,
                transform: transform
                    ? `translate(${transform.x / escala}px, ${transform.y / escala}px)`
                    : undefined,
            }}
        >
            {children}
            {onElegir && (
                <button
                    {...listeners}
                    {...attributes}
                    type="button"
                    aria-label={`${etiqueta}. Arrastra para mover o usa Espacio, flechas y Espacio.`}
                    aria-pressed={activa === zona}
                    onClick={() => onElegir(zona)}
                    className="absolute -top-3 right-0 z-10 min-h-6 rounded border border-blue-700 bg-white px-2 font-sans text-[10px] text-blue-800 focus-visible:outline-2 focus-visible:outline-blue-700"
                    style={{ touchAction: 'none' }}
                >
                    Mover
                </button>
            )}
        </div>
    );
}

function DiplomaEditable({
    datos,
    zonaActiva,
    onElegirZona,
    onCambiarTexto,
}: {
    datos: HojaDatos;
    zonaActiva: ZonaCertificado | null;
    onElegirZona?: (zona: ZonaCertificado) => void;
    onCambiarTexto?: (campo: TextoCertificado, valor: string) => void;
}) {
    const texto = (campo: TextoCertificado, etiqueta: string) => (
        <TextoEditable
            valor={datos[campo]}
            etiqueta={etiqueta}
            onCambiar={
                onCambiarTexto
                    ? (valor) => onCambiarTexto(campo, valor)
                    : undefined
            }
        />
    );
    const zona = (
        nombre: ZonaCertificado,
        children: ReactNode,
        style: CSSProperties,
    ) => (
        <div style={{ position: 'absolute', ...style }}>
            <Zona
                zona={nombre}
                activa={zonaActiva}
                etiqueta={`Editar ${nombre}`}
                onElegir={onElegirZona}
            >
                {children}
            </Zona>
        </div>
    );
    const firmante = datos.firmantes[0];
    return (
        <div
            style={{
                position: 'absolute',
                inset: 0,
                fontFamily: 'CertTexto',
                textAlign: 'center',
            }}
        >
            <img
                src={franjaSuperior}
                alt=""
                style={{ position: 'absolute', top: 0, left: 0, width: 173 }}
            />
            <img
                src={franjaInferior}
                alt=""
                style={{
                    position: 'absolute',
                    bottom: 0,
                    right: 0,
                    width: 173,
                }}
            />
            <img
                src={marcaAgua}
                alt=""
                style={{
                    position: 'absolute',
                    top: 119,
                    left: 269,
                    width: 303,
                }}
            />
            {zona(
                'encabezado',
                <>
                    <img
                        src={logo}
                        alt="Extintores Bruce Fire"
                        style={{ width: 425 }}
                    />
                    <div
                        style={{
                            position: 'absolute',
                            top: 102,
                            left: 0,
                            width: '100%',
                            fontFamily: 'CertTitulo',
                            fontSize: 55,
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {texto('titulo', 'Título')}
                    </div>
                    <div
                        style={{
                            position: 'absolute',
                            top: 170,
                            left: 0,
                            width: '100%',
                            color: C.rojo,
                            fontFamily: 'CertCond',
                            fontSize: 16,
                            letterSpacing: 6,
                        }}
                    >
                        {texto('subtitulo', 'Subtítulo')}
                    </div>
                </>,
                { top: 31, left: 85, width: 672 },
            )}
            {zona(
                'datos',
                <>
                    <div
                        style={{
                            fontFamily: 'CertCond',
                            fontSize: 12,
                            letterSpacing: 1.4,
                        }}
                    >
                        OTORGADO A
                    </div>
                    <div
                        style={{
                            marginTop: 24,
                            fontFamily: 'CertSub',
                            fontSize: 26,
                        }}
                    >
                        COMERCIAL LOS ANDES S.A.C.
                    </div>
                    <div
                        style={{
                            margin: '8px 60px 0',
                            borderTop: `1px solid ${C.rojo}`,
                        }}
                    />
                </>,
                { top: 249, left: 85, width: 672 },
            )}
            {zona(
                'tabla',
                <>
                    <div style={{ fontSize: 11.8 }}>
                        {texto('declaracion', 'Declaración')} en:
                    </div>
                    <div
                        style={{
                            marginTop: 12,
                            fontFamily: 'CertSub',
                            fontSize: 21,
                            color: C.rojo,
                        }}
                    >
                        USO Y MANEJO DE EXTINTORES
                    </div>
                    <div style={{ marginTop: 17, fontSize: 11.8 }}>
                        Con una duración de <b>04 horas</b>, realizada en
                        Trujillo el 18 de octubre de 2026.
                    </div>
                </>,
                { top: 344, left: 71, width: 700 },
            )}
            {zona(
                'cierre',
                <div style={{ fontSize: 9 }}>
                    {texto('responsabilidad', 'Responsabilidad')}
                </div>,
                { top: 435, left: 71, width: 700 },
            )}
            {zona(
                'pruebas',
                <div
                    style={{
                        border: '1px solid #ddd',
                        borderRadius: 9,
                        padding: 12,
                        textAlign: 'left',
                        fontSize: 10,
                    }}
                >
                    <b style={{ color: C.rojo }}>{datos.prefijo}-0000001</b>
                    <br />
                    ESCANEE PARA VERIFICAR SU AUTENTICIDAD
                    <div style={{ fontSize: 8, marginTop: 5 }}>
                        Certificado válido · Vista de ejemplo
                    </div>
                </div>,
                { top: 462, left: 91, width: 220 },
            )}
            {zona(
                'firmas',
                <div style={{ width: 185, textAlign: 'center' }}>
                    {firmante?.firma_url ? (
                        <img
                            src={firmante.firma_url}
                            alt="Firma"
                            style={{ height: 40 }}
                        />
                    ) : (
                        <div style={{ height: 40 }} />
                    )}
                    <div
                        style={{
                            borderTop: '1px solid',
                            paddingTop: 4,
                            fontFamily: 'CertCond',
                            fontSize: 11,
                        }}
                    >
                        {firmante?.nombre}
                    </div>
                    <div
                        style={{
                            fontFamily: 'CertCond',
                            color: C.rojo,
                            fontSize: 8.4,
                        }}
                    >
                        {firmante?.cargo || 'Instructor'}
                    </div>
                    <img
                        src={sello}
                        alt="Sello Bruce Fire"
                        style={{
                            position: 'absolute',
                            top: -25,
                            left: -187,
                            width: 102,
                        }}
                    />
                </div>,
                { top: 465, left: 550, width: 185 },
            )}
        </div>
    );
}

/**
 * Vista previa del certificado de operatividad tal como sale en el PDF, con
 * datos de ejemplo. Se ajusta al espacio disponible sin cambiar sus medidas.
 */
export default function HojaCertificado({
    datos,
    zonaActiva = null,
    onElegirZona,
    onCambiarTexto,
    onCambiarColumna,
    onCambiarPrueba,
    onMover,
}: {
    datos: HojaDatos;
    zonaActiva?: ZonaCertificado | null;
    onElegirZona?: (zona: ZonaCertificado) => void;
    onCambiarTexto?: (campo: TextoCertificado, valor: string) => void;
    onCambiarColumna?: (indice: number, titulo: string) => void;
    onCambiarPrueba?: (indice: number, texto: string) => void;
    onMover?: (
        zona: ZonaCertificado,
        posicion: { x: number; y: number },
    ) => void;
}) {
    const sensores = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor),
    );
    const contenedor = useRef<HTMLDivElement>(null);
    const [escala, setEscala] = useState(1);
    const horizontal = datos.orientacion === 'horizontal';
    const { ancho, alto } = MEDIDA[datos.orientacion];

    useEffect(() => {
        const nodo = contenedor.current;
        if (!nodo) return;
        const medir = () =>
            setEscala(
                Math.min(
                    nodo.clientWidth / ancho,
                    nodo.clientHeight / alto,
                    1.4,
                ),
            );
        medir();
        const observador = new ResizeObserver(medir);
        observador.observe(nodo);
        return () => observador.disconnect();
    }, [ancho, alto]);

    const palabras = (datos.titulo || 'CERTIFICADO').toUpperCase().split(' ');
    const linea1 = palabras[0];
    const linea2 = palabras.slice(1).join(' ');
    const subtitulo = datos.subtitulo.toUpperCase();
    const numero = `${datos.prefijo || '___'}-0000001`;
    const celda: CSSProperties = {
        padding: '1.6px 4.5px',
        borderBottom: `0.6px solid ${C.linea}`,
        fontFamily: 'CertCond',
        fontWeight: 700,
        fontSize: horizontal ? 8.6 : 8.2,
    };
    const datosCliente = [
        ['Razón social', 'COMERCIAL LOS ANDES S.A.C.'],
        ['RUC', '20481234567'],
        ['Nombre comercial', 'Market Los Andes'],
        ['Dirección', 'Av. España 1245, Trujillo'],
    ];
    const datosServicio = [
        ['Fecha de emisión', '18/10/2026'],
        ['Vencimiento', datos.vigenciaMeses > 0 ? '18/10/2027' : 'No vence'],
        [
            'Vigencia',
            datos.vigenciaMeses > 0 ? `${datos.vigenciaMeses} meses` : '—',
        ],
        ['Orden de servicio', 'OS-2026-0142'],
    ];
    const mitad = Math.ceil(datos.pruebas.length / 2);
    // Como el PDF: el sello va entre las firmas, en la mitad.
    const posicionSello = Math.floor(datos.firmantes.length / 2);
    const pieDeFirmas = [
        ...datos.firmantes.slice(0, posicionSello),
        null,
        ...datos.firmantes.slice(posicionSello),
    ];

    return (
        <DndContext
            sensors={sensores}
            onDragStart={({ active }) =>
                onElegirZona?.(active.id as ZonaCertificado)
            }
            onDragEnd={({ active, delta }) => {
                const zona = active.id as ZonaCertificado;
                const actual = datos.diseno?.[zona] ?? { x: 0, y: 0 };
                onMover?.(zona, {
                    x: Math.max(
                        -24,
                        Math.min(24, Math.round(actual.x + delta.x / escala)),
                    ),
                    y: Math.max(
                        -24,
                        Math.min(24, Math.round(actual.y + delta.y / escala)),
                    ),
                });
            }}
        >
            <Edicion.Provider value={{ diseno: datos.diseno ?? {}, escala }}>
                <div
                    ref={contenedor}
                    className="absolute inset-0 flex items-center justify-center overflow-hidden"
                >
                    <style>{fuentes}</style>
                    <div
                        style={{
                            width: ancho * escala,
                            height: alto * escala,
                            flexShrink: 0,
                        }}
                    >
                        <div
                            aria-label="Vista previa del certificado"
                            style={{
                                width: ancho,
                                height: alto,
                                transform: `scale(${escala})`,
                                transformOrigin: 'top left',
                                position: 'relative',
                                overflow: 'hidden',
                                background: '#fff',
                                color: C.texto,
                                boxShadow:
                                    '0 1px 2px rgba(0,0,0,.08), 0 12px 32px rgba(0,0,0,.16)',
                                padding: '28px 37px 40px',
                                boxSizing: 'border-box',
                                fontFamily: 'CertTexto',
                                fontSize: 8.4,
                                lineHeight: 1.28,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 6,
                            }}
                        >
                            {datos.familia === 'diploma' ? (
                                <DiplomaEditable
                                    datos={datos}
                                    zonaActiva={zonaActiva}
                                    onElegirZona={onElegirZona}
                                    onCambiarTexto={onCambiarTexto}
                                />
                            ) : (
                                <>
                                    <img
                                        src={franjaSuperior}
                                        alt=""
                                        style={{
                                            position: 'absolute',
                                            top: 0,
                                            left: 0,
                                            width: 159,
                                        }}
                                    />
                                    <img
                                        src={franjaInferior}
                                        alt=""
                                        style={{
                                            position: 'absolute',
                                            bottom: 0,
                                            right: 0,
                                            width: 159,
                                        }}
                                    />
                                    <img
                                        src={marcaAgua}
                                        alt=""
                                        style={{
                                            position: 'absolute',
                                            width: 283,
                                            top: horizontal ? 175 : 305,
                                            left: horizontal ? 280 : 156,
                                        }}
                                    />

                                    <Zona
                                        zona="encabezado"
                                        activa={zonaActiva}
                                        etiqueta="Editar encabezado"
                                        onElegir={onElegirZona}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                            }}
                                        >
                                            <img
                                                src={logo}
                                                alt="Bruce Fire"
                                                style={{
                                                    width: horizontal
                                                        ? 280
                                                        : 318,
                                                }}
                                            />
                                            <div
                                                style={{
                                                    border: `1.1px solid ${C.rojo}`,
                                                    borderRadius: 4,
                                                    padding: '1.5px 7px',
                                                    fontFamily: 'CertCond',
                                                    fontWeight: 700,
                                                    fontSize: 11.5,
                                                    color: C.rojo,
                                                }}
                                            >
                                                RUC:{' '}
                                                <span
                                                    style={{
                                                        fontFamily: 'CertMono',
                                                        color: C.texto,
                                                    }}
                                                >
                                                    20616376528
                                                </span>
                                            </div>
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                gap: 12,
                                                marginTop: 4,
                                            }}
                                        >
                                            <div style={{ minWidth: 0 }}>
                                                <p
                                                    style={{
                                                        margin: 0,
                                                        fontFamily:
                                                            'CertTitulo',
                                                        lineHeight: 0.96,
                                                        fontSize: tamano(
                                                            linea1,
                                                            40,
                                                            11,
                                                        ),
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    <TextoEditable
                                                        valor={linea1}
                                                        etiqueta="Título, primera línea"
                                                        onCambiar={
                                                            onCambiarTexto
                                                                ? (valor) =>
                                                                      onCambiarTexto(
                                                                          'titulo',
                                                                          `${valor} ${linea2}`.trim(),
                                                                      )
                                                                : undefined
                                                        }
                                                    />
                                                </p>
                                                {linea2 && (
                                                    <p
                                                        style={{
                                                            margin: 0,
                                                            fontFamily:
                                                                'CertTitulo',
                                                            lineHeight: 0.96,
                                                            fontSize: tamano(
                                                                linea2,
                                                                27,
                                                                16,
                                                            ),
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        <TextoEditable
                                                            valor={linea2}
                                                            etiqueta="Título, segunda línea"
                                                            onCambiar={
                                                                onCambiarTexto
                                                                    ? (valor) =>
                                                                          onCambiarTexto(
                                                                              'titulo',
                                                                              `${linea1} ${valor}`.trim(),
                                                                          )
                                                                    : undefined
                                                            }
                                                        />
                                                    </p>
                                                )}
                                                {subtitulo && (
                                                    <p
                                                        style={{
                                                            margin: '2px 0 0',
                                                            fontFamily:
                                                                'CertSub',
                                                            color: C.rojo,
                                                            lineHeight: 1.02,
                                                            fontSize: tamano(
                                                                subtitulo,
                                                                19,
                                                                horizontal
                                                                    ? 34
                                                                    : 22,
                                                            ),
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        <TextoEditable
                                                            valor={subtitulo}
                                                            etiqueta="Subtítulo"
                                                            onCambiar={
                                                                onCambiarTexto
                                                                    ? (valor) =>
                                                                          onCambiarTexto(
                                                                              'subtitulo',
                                                                              valor,
                                                                          )
                                                                    : undefined
                                                            }
                                                        />
                                                    </p>
                                                )}
                                            </div>
                                            <div
                                                style={{
                                                    width: 187,
                                                    flexShrink: 0,
                                                    border: '0.8px solid #cfcfcf',
                                                    borderRadius: 6,
                                                    padding: 5,
                                                    display: 'flex',
                                                    gap: 6,
                                                    alignItems: 'center',
                                                }}
                                            >
                                                <div
                                                    aria-hidden="true"
                                                    style={{
                                                        width: 62,
                                                        height: 62,
                                                        flexShrink: 0,
                                                        background:
                                                            'repeating-conic-gradient(#1b1918 0 25%, #fff 0 50%) 0 0 / 10px 10px',
                                                        border: '4px solid #fff',
                                                        outline:
                                                            '1px solid #1b1918',
                                                    }}
                                                />
                                                <div style={{ minWidth: 0 }}>
                                                    <p
                                                        style={{
                                                            margin: 0,
                                                            fontFamily:
                                                                'CertCond',
                                                            fontWeight: 700,
                                                            fontSize: 6.8,
                                                            color: C.gris,
                                                            letterSpacing: 0.8,
                                                        }}
                                                    >
                                                        N.° DE CERTIFICADO
                                                    </p>
                                                    <p
                                                        style={{
                                                            margin: '1px 0 0',
                                                            fontFamily:
                                                                'CertMono',
                                                            fontSize: 11.5,
                                                        }}
                                                    >
                                                        {numero}
                                                    </p>
                                                    <div
                                                        style={{
                                                            marginTop: 4,
                                                            background: C.verde,
                                                            color: '#fff',
                                                            borderRadius: 4,
                                                            padding: '3px 4px',
                                                            textAlign: 'center',
                                                            fontFamily:
                                                                'CertCond',
                                                            fontWeight: 700,
                                                        }}
                                                    >
                                                        <p
                                                            style={{
                                                                margin: 0,
                                                                fontSize: 7.6,
                                                                letterSpacing: 0.6,
                                                            }}
                                                        >
                                                            VIGENTE HASTA
                                                        </p>
                                                        <p
                                                            style={{
                                                                margin: 0,
                                                                fontSize: 14,
                                                                lineHeight: 1.05,
                                                            }}
                                                        >
                                                            {datos.vigenciaMeses >
                                                            0
                                                                ? '18/10/2027'
                                                                : 'NO VENCE'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {datos.norma && (
                                            <p
                                                style={{
                                                    margin: '4px 0 0',
                                                    fontSize: 8.8,
                                                    color: '#3f3d3b',
                                                    textAlign: 'center',
                                                }}
                                            >
                                                <TextoEditable
                                                    valor={datos.norma}
                                                    etiqueta="Norma"
                                                    onCambiar={
                                                        onCambiarTexto
                                                            ? (valor) =>
                                                                  onCambiarTexto(
                                                                      'norma',
                                                                      valor,
                                                                  )
                                                            : undefined
                                                    }
                                                />
                                            </p>
                                        )}
                                    </Zona>

                                    <Zona
                                        zona="datos"
                                        activa={zonaActiva}
                                        etiqueta="Ver datos del cliente y del servicio"
                                        onElegir={onElegirZona}
                                    >
                                        <div
                                            style={{
                                                display: 'grid',
                                                gridTemplateColumns:
                                                    'repeat(2, minmax(0, 1fr))',
                                                gap: 5,
                                            }}
                                        >
                                            {[
                                                [
                                                    'Datos del cliente',
                                                    datosCliente,
                                                ],
                                                [
                                                    'Datos del servicio',
                                                    datosServicio,
                                                ],
                                            ].map(([titulo, filas]) => (
                                                <div key={titulo as string}>
                                                    <Barra>
                                                        {titulo as string}
                                                    </Barra>
                                                    {(filas as string[][]).map(
                                                        ([k, v]) => (
                                                            <div
                                                                key={k}
                                                                style={{
                                                                    display:
                                                                        'grid',
                                                                    gridTemplateColumns:
                                                                        '91px minmax(0, 1fr)',
                                                                    borderBottom: `0.6px solid ${C.linea}`,
                                                                }}
                                                            >
                                                                <span
                                                                    style={{
                                                                        ...celda,
                                                                        borderBottom: 0,
                                                                        background:
                                                                            C.fondo,
                                                                        color: C.rojo,
                                                                    }}
                                                                >
                                                                    {k}
                                                                </span>
                                                                <span
                                                                    style={{
                                                                        ...celda,
                                                                        borderBottom: 0,
                                                                    }}
                                                                >
                                                                    {v}
                                                                </span>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </Zona>

                                    {datos.declaracion && (
                                        <p
                                            style={{
                                                margin: 0,
                                                fontSize: 8.5,
                                                lineHeight: 1.3,
                                                textAlign: 'justify',
                                            }}
                                        >
                                            <TextoEditable
                                                valor={datos.declaracion}
                                                etiqueta="Declaración"
                                                onCambiar={
                                                    onCambiarTexto
                                                        ? (valor) =>
                                                              onCambiarTexto(
                                                                  'declaracion',
                                                                  valor,
                                                              )
                                                        : undefined
                                                }
                                            />
                                        </p>
                                    )}

                                    <Zona
                                        zona="tabla"
                                        activa={zonaActiva}
                                        etiqueta="Editar tabla de equipos"
                                        onElegir={onElegirZona}
                                    >
                                        <Barra>Equipos inspeccionados</Barra>
                                        <table
                                            style={{
                                                width: '100%',
                                                borderCollapse: 'collapse',
                                            }}
                                        >
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Ítem',
                                                        ...datos.columnas.map(
                                                            (c) =>
                                                                c.titulo ||
                                                                'Sin nombre',
                                                        ),
                                                        ...(datos.tieneResultado
                                                            ? ['Resultado']
                                                            : []),
                                                    ].map((titulo, i) => (
                                                        <th
                                                            key={i}
                                                            style={{
                                                                ...celda,
                                                                background:
                                                                    '#f1f0ef',
                                                                textAlign:
                                                                    'left',
                                                            }}
                                                        >
                                                            <TextoEditable
                                                                valor={titulo}
                                                                etiqueta={`Columna ${i}`}
                                                                onCambiar={
                                                                    onCambiarColumna &&
                                                                    i > 0 &&
                                                                    i <=
                                                                        datos
                                                                            .columnas
                                                                            .length
                                                                        ? (
                                                                              valor,
                                                                          ) =>
                                                                              onCambiarColumna(
                                                                                  i -
                                                                                      1,
                                                                                  valor,
                                                                              )
                                                                        : undefined
                                                                }
                                                            />
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {Array.from(
                                                    { length: EJEMPLO_FILAS },
                                                    (_, fila) => (
                                                        <tr key={fila}>
                                                            <td
                                                                style={{
                                                                    ...celda,
                                                                    fontWeight: 400,
                                                                }}
                                                            >
                                                                {String(
                                                                    fila + 1,
                                                                ).padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                            </td>
                                                            {datos.columnas.map(
                                                                (c, i) => (
                                                                    <td
                                                                        key={i}
                                                                        style={{
                                                                            ...celda,
                                                                            fontWeight: 400,
                                                                            color: '#9a9794',
                                                                        }}
                                                                    >
                                                                        {c.unidad
                                                                            ? `— ${c.unidad}`
                                                                            : '—'}
                                                                    </td>
                                                                ),
                                                            )}
                                                            {datos.tieneResultado && (
                                                                <td
                                                                    style={{
                                                                        ...celda,
                                                                        textAlign:
                                                                            'center',
                                                                    }}
                                                                >
                                                                    <span
                                                                        style={{
                                                                            background:
                                                                                C.verde,
                                                                            color: '#fff',
                                                                            borderRadius: 2.5,
                                                                            padding:
                                                                                '0.5px 5px',
                                                                            fontSize: 7.2,
                                                                        }}
                                                                    >
                                                                        OPERATIVO
                                                                    </span>
                                                                </td>
                                                            )}
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </Zona>

                                    <Zona
                                        zona="pruebas"
                                        activa={zonaActiva}
                                        etiqueta="Editar pruebas realizadas"
                                        onElegir={onElegirZona}
                                    >
                                        <div
                                            style={{
                                                display: 'grid',
                                                gridTemplateColumns: horizontal
                                                    ? 'minmax(0, 2fr) minmax(0, 1fr)'
                                                    : '1fr',
                                                gap: 6,
                                            }}
                                        >
                                            <div>
                                                <Barra>
                                                    Pruebas realizadas
                                                </Barra>
                                                <div
                                                    style={{
                                                        display: 'grid',
                                                        gridTemplateColumns:
                                                            'repeat(2, minmax(0, 1fr))',
                                                        columnGap: 6,
                                                    }}
                                                >
                                                    {[
                                                        datos.pruebas.slice(
                                                            0,
                                                            mitad,
                                                        ),
                                                        datos.pruebas.slice(
                                                            mitad,
                                                        ),
                                                    ].map((grupo, g) => (
                                                        <div key={g}>
                                                            {grupo.map(
                                                                (texto, i) => (
                                                                    <div
                                                                        key={i}
                                                                        style={{
                                                                            display:
                                                                                'grid',
                                                                            gridTemplateColumns:
                                                                                '18px minmax(0, 1fr) 36px',
                                                                            alignItems:
                                                                                'center',
                                                                            borderBottom: `0.6px solid ${C.linea}`,
                                                                            padding:
                                                                                '1.5px 0',
                                                                        }}
                                                                    >
                                                                        <span
                                                                            style={{
                                                                                color: C.rojo,
                                                                                fontFamily:
                                                                                    'CertCond',
                                                                                fontWeight: 700,
                                                                            }}
                                                                        >
                                                                            {String(
                                                                                g *
                                                                                    mitad +
                                                                                    i +
                                                                                    1,
                                                                            ).padStart(
                                                                                2,
                                                                                '0',
                                                                            )}
                                                                        </span>
                                                                        <span
                                                                            style={{
                                                                                fontFamily:
                                                                                    'CertCond',
                                                                                fontWeight: 700,
                                                                                fontSize: 8,
                                                                            }}
                                                                        >
                                                                            <TextoEditable
                                                                                valor={
                                                                                    texto ||
                                                                                    'Sin texto'
                                                                                }
                                                                                etiqueta={`Prueba ${g * mitad + i + 1}`}
                                                                                onCambiar={
                                                                                    onCambiarPrueba
                                                                                        ? (
                                                                                              valor,
                                                                                          ) =>
                                                                                              onCambiarPrueba(
                                                                                                  g *
                                                                                                      mitad +
                                                                                                      i,
                                                                                                  valor,
                                                                                              )
                                                                                        : undefined
                                                                                }
                                                                            />
                                                                        </span>
                                                                        <span
                                                                            style={{
                                                                                display:
                                                                                    'flex',
                                                                                gap: 3,
                                                                            }}
                                                                        >
                                                                            <span
                                                                                style={{
                                                                                    width: 9,
                                                                                    height: 9,
                                                                                    background:
                                                                                        C.verde,
                                                                                    borderRadius: 1.5,
                                                                                }}
                                                                            />
                                                                            <span
                                                                                style={{
                                                                                    width: 9,
                                                                                    height: 9,
                                                                                    border: '0.8px solid #b5b3b0',
                                                                                    borderRadius: 1.5,
                                                                                }}
                                                                            />
                                                                            <span
                                                                                style={{
                                                                                    width: 9,
                                                                                    height: 9,
                                                                                    border: '0.8px solid #b5b3b0',
                                                                                    borderRadius: 1.5,
                                                                                }}
                                                                            />
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                            <div>
                                                <Barra>
                                                    Resultado de la evaluación
                                                </Barra>
                                                <div
                                                    style={{
                                                        display: 'grid',
                                                        gridTemplateColumns:
                                                            horizontal
                                                                ? '1fr'
                                                                : 'repeat(3, minmax(0, 1fr))',
                                                        fontFamily: 'CertCond',
                                                        fontWeight: 700,
                                                        fontSize: 9,
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            background:
                                                                '#e8f3ec',
                                                            color: C.verde,
                                                            padding: '4px 7px',
                                                        }}
                                                    >
                                                        OPERATIVO
                                                    </span>
                                                    <span
                                                        style={{
                                                            padding: '4px 7px',
                                                            color: '#8a8784',
                                                        }}
                                                    >
                                                        OPERATIVO CON
                                                        OBSERVACIÓN
                                                    </span>
                                                    <span
                                                        style={{
                                                            padding: '4px 7px',
                                                            color: '#8a8784',
                                                        }}
                                                    >
                                                        NO OPERATIVO
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </Zona>

                                    <div style={{ marginTop: 'auto' }}>
                                        <Zona
                                            zona="cierre"
                                            activa={zonaActiva}
                                            etiqueta="Editar la nota final"
                                            onElegir={onElegirZona}
                                        >
                                            <p
                                                style={{
                                                    margin: '0 0 14px',
                                                    fontSize: 6.8,
                                                    color: C.gris,
                                                    lineHeight: 1.35,
                                                    minHeight: 9,
                                                }}
                                            >
                                                <TextoEditable
                                                    valor={
                                                        datos.responsabilidad
                                                    }
                                                    etiqueta="Responsabilidad"
                                                    onCambiar={
                                                        onCambiarTexto
                                                            ? (valor) =>
                                                                  onCambiarTexto(
                                                                      'responsabilidad',
                                                                      valor,
                                                                  )
                                                            : undefined
                                                    }
                                                />
                                            </p>
                                        </Zona>
                                        <Zona
                                            zona="firmas"
                                            activa={zonaActiva}
                                            etiqueta="Editar firmas y sello"
                                            onElegir={onElegirZona}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    justifyContent: 'center',
                                                    alignItems: 'flex-end',
                                                    gap: 16,
                                                    textAlign: 'center',
                                                    minHeight: 44,
                                                }}
                                            >
                                                {pieDeFirmas.map(
                                                    (firmante, i) =>
                                                        firmante === null ? (
                                                            <img
                                                                key="sello"
                                                                src={sello}
                                                                alt="Sello Bruce Fire"
                                                                style={{
                                                                    width: 60,
                                                                    flexShrink: 0,
                                                                }}
                                                            />
                                                        ) : (
                                                            <div
                                                                key={i}
                                                                style={{
                                                                    flex: '1 1 0',
                                                                    maxWidth: 170,
                                                                    minWidth: 0,
                                                                }}
                                                            >
                                                                <div
                                                                    style={{
                                                                        height: 26,
                                                                        display:
                                                                            'flex',
                                                                        alignItems:
                                                                            'flex-end',
                                                                        justifyContent:
                                                                            'center',
                                                                    }}
                                                                >
                                                                    {firmante.firma_url && (
                                                                        <img
                                                                            src={
                                                                                firmante.firma_url
                                                                            }
                                                                            alt=""
                                                                            style={{
                                                                                maxHeight: 26,
                                                                                maxWidth:
                                                                                    '80%',
                                                                                objectFit:
                                                                                    'contain',
                                                                            }}
                                                                        />
                                                                    )}
                                                                </div>
                                                                <div
                                                                    style={{
                                                                        borderTop: `0.8px solid ${C.texto}`,
                                                                        paddingTop: 3,
                                                                    }}
                                                                >
                                                                    <b
                                                                        style={{
                                                                            fontFamily:
                                                                                'CertCond',
                                                                            fontSize: 8.6,
                                                                        }}
                                                                    >
                                                                        {
                                                                            firmante.nombre
                                                                        }
                                                                    </b>
                                                                    <span
                                                                        style={{
                                                                            display:
                                                                                'block',
                                                                            fontSize: 7,
                                                                            color: C.gris,
                                                                            textTransform:
                                                                                'uppercase',
                                                                        }}
                                                                    >
                                                                        {
                                                                            firmante.cargo
                                                                        }
                                                                    </span>
                                                                    {firmante.cip && (
                                                                        <span
                                                                            style={{
                                                                                display:
                                                                                    'block',
                                                                                fontSize: 7,
                                                                                color: C.gris,
                                                                            }}
                                                                        >
                                                                            CIP
                                                                            N.°{' '}
                                                                            {
                                                                                firmante.cip
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ),
                                                )}
                                            </div>
                                        </Zona>
                                        <p
                                            style={{
                                                margin: '8px 0 0',
                                                textAlign: 'center',
                                                fontSize: 6.5,
                                                color: C.gris,
                                            }}
                                        >
                                            BRUCE FIRE S.A.C. · RUC 20616376528
                                            · Trujillo · Página 1 de 1
                                        </p>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </Edicion.Provider>
        </DndContext>
    );
}
