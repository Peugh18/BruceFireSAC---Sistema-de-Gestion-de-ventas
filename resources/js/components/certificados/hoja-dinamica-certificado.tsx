import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';

import logo from '../../../images/brand/bruce-fire-logo-grafito.png';
import asneex from '../../../images/certificados/logo-asneex.png';
import cola from '../../../images/certificados/cola-seccion.png';
import franjaInferior from '../../../images/certificados/franja-inferior-derecha.png';
import franjaSuperior from '../../../images/certificados/franja-superior-izquierda.png';
import iconoGmail from '../../../images/certificados/icono-gmail.png';
import iconoWhatsapp from '../../../images/certificados/icono-whatsapp.png';
import marcaAgua from '../../../images/certificados/marca-agua.png';
import sello from '../../../images/certificados/sello-con-texto.png';
// Las mismas fuentes que el PDF (CertificatePdfService::fuentes).
import condNegrita from '../../../fonts/barlow-semicondensed-bold.ttf';
import cond from '../../../fonts/barlow-semicondensed-medium.ttf';
import texto from '../../../fonts/barlow-regular.ttf';
import textoNegrita from '../../../fonts/barlow-semibold.ttf';
import mono from '../../../fonts/ibmplexmono-medium.ttf';
import monoNegrita from '../../../fonts/ibmplexmono-semibold.ttf';
import titulo from '../../../fonts/montserrat-black-italic.ttf';
import subtitulo from '../../../fonts/montserrat-extrabold-italic.ttf';
import type {
    BloqueCertificado,
    ColumnaCertificado,
    PruebaCertificado,
} from './composicion-certificado';

type Datos = {
    titulo: string;
    subtitulo: string;
    norma: string;
    declaracion: string;
    responsabilidad: string;
    prefijo: string;
    vigencia_meses: number;
    orientacion: 'vertical' | 'horizontal';
    composicion: BloqueCertificado[];
    columnas: ColumnaCertificado[];
    checklist: PruebaCertificado[];
    firmantes: {
        nombre: string;
        cargo: string;
        cip: string | null;
        firma_url: string | null;
    }[];
};

type Firmante = Datos['firmantes'][number];

/**
 * Réplica de certificado-operatividad.blade.php y certificado-diploma.blade.php
 * con las mismas medidas (mm y pt), para que la vista previa sea el PDF.
 */
const estilos = `
@font-face{font-family:DinTitulo;src:url(${titulo})}
@font-face{font-family:DinSubtitulo;src:url(${subtitulo})}
@font-face{font-family:DinCond;src:url(${cond})}
@font-face{font-family:DinCond;src:url(${condNegrita});font-weight:700}
@font-face{font-family:DinTexto;src:url(${texto})}
@font-face{font-family:DinTexto;src:url(${textoNegrita});font-weight:700}
@font-face{font-family:DinMono;src:url(${mono})}
@font-face{font-family:DinMono;src:url(${monoNegrita});font-weight:700}
.hd{font-family:DinTexto,sans-serif;font-size:8.4pt;color:#1b1918;line-height:1.28}
.hd *{box-sizing:border-box}
.hd table{border-collapse:collapse;width:100%}
.hd td,.hd th{padding:0;vertical-align:top}
.hd .mono{font-family:DinMono,monospace;font-weight:700}
.hd .seccion{margin-top:2.2mm}
.hd .barra td{vertical-align:middle}
.hd .barra .t{background:#2e2d2c;border-left:1.3mm solid #d20404;color:#fff;font-family:DinCond;font-weight:700;font-size:9.8pt;letter-spacing:.5pt;text-transform:uppercase;padding:.85mm 2.6mm;height:5.8mm}
.hd .barra .cola{width:5.4mm}
.hd .barra .cola img{width:5.4mm;height:5.8mm;display:block}
.hd .datos{border:.6pt solid #d9d9d9;border-top:0}
.hd .datos td{border-bottom:.6pt solid #e6e6e6;padding:.6mm 2.4mm;vertical-align:middle}
.hd .datos .e{width:32mm;background:#f6f5f4;color:#d20404;font-family:DinCond;font-weight:700;font-size:8.6pt;border-right:.6pt solid #e6e6e6}
.hd .datos .v{font-family:DinCond;font-weight:700;font-size:8.8pt}
.hd .declaracion{margin:2mm 0 0;font-size:8.5pt;line-height:1.3;text-align:justify;white-space:pre-line}
.hd .tabla{border:.6pt solid #d9d9d9;border-top:0}
.hd .tabla th{background:#f1f0ef;font-family:DinCond;font-weight:700;font-size:8.4pt;padding:.7mm 1.6mm;text-align:left;border-bottom:.6pt solid #d9d9d9;border-right:.6pt solid #e4e4e4}
.hd .tabla td{padding:.5mm 1.6mm;font-family:DinCond;font-size:8.6pt;border-bottom:.6pt solid #e8e8e8;border-right:.6pt solid #eee;vertical-align:middle}
.hd .tabla tr.par td{background:#fbfaf9}
.hd .centro{text-align:center!important}
.hd .pastilla{padding:.35mm 2mm;border-radius:2.5pt;font-family:DinCond;font-weight:700;font-size:7.2pt;letter-spacing:.3pt;color:#fff;background:#0e7a3e}
.hd .pruebas th{font-size:7.4pt;letter-spacing:.4pt;text-transform:uppercase;padding:.6mm 1.2mm}
.hd .pruebas td{font-size:7.9pt;padding:.35mm 1.2mm}
.hd .pruebas .n{width:6.5mm;color:#d20404;font-weight:700}
.hd .pruebas .m{width:6.4mm;text-align:center;padding:.35mm 0}
.hd .caja{display:inline-block;width:3.2mm;height:3.2mm;border:.8pt solid #9c9a98;border-radius:.8pt;text-align:center;line-height:3mm;font-size:7pt;vertical-align:middle}
.hd .caja-c{border-color:#0e7a3e;color:#fff;background:#0e7a3e}
.hd .resultado{border:.6pt solid #d9d9d9;border-top:0}
.hd .resultado .celda{width:33.3%;padding:1.4mm 2.4mm;vertical-align:middle;border-right:.6pt solid #e4e4e4;background:#f7f7f6}
.hd .resultado .elegida{background:#e7f5ec}
.hd .opcion{display:flex;align-items:center;gap:2.4mm;font-family:DinCond;font-weight:700;font-size:10.4pt;color:#5c5a58;line-height:1.1}
.hd .elegida .opcion{color:#0b5d30}
.hd .opcion .caja{width:5mm;height:5mm;line-height:4.6mm;font-size:10pt;border-width:1pt;flex-shrink:0}
.hd .responsabilidad{margin:1.5mm 0 0;font-size:7.1pt;color:#5c5a58;text-align:justify;line-height:1.32;white-space:pre-line}
.hd .firmas{margin-top:1.5mm}
.hd .firmas td{text-align:center;vertical-align:top;padding:0 2.5mm}
.hd .firma-img{height:10mm;margin-top:2mm}
.hd .firma-vacia{height:12mm}
.hd .firma-linea{border-top:.8pt solid #1b1918;padding-top:.9mm}
.hd .firma-nombre{font-family:DinCond;font-weight:700;font-size:9pt}
.hd .firma-cargo{font-family:DinCond;font-size:7.4pt;color:#3f3d3b;text-transform:uppercase;letter-spacing:.3pt;line-height:1.2}
.hd .tarjeta{border:.8pt solid #cfcfcf;border-radius:6pt;padding:1.8mm;background:#fff;display:flex;align-items:center;gap:1.4mm}
.hd .qr{width:23mm;height:23mm;flex-shrink:0;background:repeating-conic-gradient(#1b1918 0 25%,#fff 0 50%) 0 0/2.3mm 2.3mm;border:1.2mm solid #fff;outline:.6pt solid #1b1918}
.hd .etiqueta{font-family:DinCond;font-weight:700;font-size:6.8pt;color:#6b6965;letter-spacing:.8pt}
.hd .estado{margin-top:1.4mm;border-radius:4pt;padding:1.1mm 1.6mm;text-align:center;color:#fff;background:#0e7a3e;font-family:DinCond;font-weight:700}
.hd .vacio{color:#9a9794}
`;

const ejemplo = {
    cliente: [
        ['Razón social', 'COMERCIAL LOS ANDES S.A.C.'],
        ['RUC', '20481234567'],
        ['Dirección', 'Av. España 1245, Trujillo'],
    ],
    telefonos: ['903 003 081', '903 005 777'],
    correo: 'area.ventas@extintoresbrucefire.com',
};

/** Ajusta el tamaño de letra para que el texto entre en su ancho, como tamanoQueEntra(). */
function Ajustado({
    maximo,
    style,
    children,
}: {
    maximo: number;
    style: CSSProperties;
    children: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [tamano, setTamano] = useState(maximo);
    useLayoutEffect(() => {
        const medir = () => {
            const elemento = ref.current;
            if (!elemento) return;
            elemento.style.fontSize = `${maximo}pt`;
            const sobra =
                elemento.scrollWidth / Math.max(elemento.clientWidth, 1);
            const nuevo =
                sobra > 1 ? Math.floor((maximo / sobra) * 9.8) / 10 : maximo;
            // También en el DOM: si el estado no cambia, React no lo repinta.
            elemento.style.fontSize = `${nuevo}pt`;
            setTamano(nuevo);
        };
        medir();
        void document.fonts?.ready.then(medir);
    }, [maximo, children]);
    return (
        <div
            ref={ref}
            style={{
                ...style,
                fontSize: `${tamano}pt`,
                whiteSpace: 'nowrap',
                overflow: 'hidden',
            }}
        >
            {children}
        </div>
    );
}

function Barra({ children }: { children: ReactNode }) {
    return (
        <table className="barra">
            <tbody>
                <tr>
                    <td className="t">{children}</td>
                    <td className="cola">
                        <img src={cola} alt="" />
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

/** pdf/partes/asneex.blade.php */
function Asneex() {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: '1.2mm',
                padding: '1mm 1.2mm',
                border: '.6pt solid #d9d9d9',
                borderTop: '1.4pt solid #d20404',
                background: '#fff',
                textAlign: 'left',
            }}
        >
            <img
                src={asneex}
                alt="ASNEEX"
                style={{ width: '10.5mm', flexShrink: 0 }}
            />
            <div style={{ fontFamily: 'DinCond', lineHeight: 1.12 }}>
                <div
                    style={{
                        fontWeight: 700,
                        fontSize: '6.3pt',
                        color: '#d20404',
                        letterSpacing: '.6pt',
                    }}
                >
                    EMPRESA ASOCIADA
                </div>
                <div
                    style={{
                        marginTop: '.5mm',
                        fontSize: '5.8pt',
                        color: '#3f3d3b',
                    }}
                >
                    Asociación Nacional de Empresas de Extintores
                </div>
                <div
                    style={{
                        marginTop: '.4mm',
                        fontWeight: 700,
                        fontSize: '6.3pt',
                        letterSpacing: '1.4pt',
                    }}
                >
                    ASNEEX
                </div>
            </div>
        </div>
    );
}

function Tarjeta({ datos, ancho }: { datos: Datos; ancho: string }) {
    return (
        <div className="tarjeta" style={{ width: ancho }}>
            <div className="qr" aria-label="QR de verificación" />
            <div style={{ minWidth: 0, flex: 1 }}>
                <div className="etiqueta">N.° DE CERTIFICADO</div>
                <div
                    className="mono"
                    style={{ fontSize: '11.5pt', whiteSpace: 'nowrap' }}
                >
                    {datos.prefijo || 'XX'}-2026-0001
                </div>
                <div className="estado">
                    <div style={{ fontSize: '7.6pt', letterSpacing: '.6pt' }}>
                        {datos.vigencia_meses > 0
                            ? 'VIGENTE HASTA'
                            : 'CERTIFICADO VÁLIDO'}
                    </div>
                    {datos.vigencia_meses > 0 && (
                        <div style={{ fontSize: '14pt', lineHeight: 1.05 }}>
                            {vencimiento(datos.vigencia_meses)}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function vencimiento(meses: number): string {
    const fecha = new Date();
    fecha.setMonth(fecha.getMonth() + meses);
    return fecha.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function Bloque({
    bloque,
    datos,
}: {
    bloque: BloqueCertificado;
    datos: Datos;
}) {
    const conResultado = datos.composicion.some((b) => b.tipo === 'resultado');
    switch (bloque.tipo) {
        case 'datos': {
            const servicio = [
                ['Fecha de emisión', new Date().toLocaleDateString('es-PE')],
                ...(datos.vigencia_meses > 0
                    ? [['Vencimiento', vencimiento(datos.vigencia_meses)]]
                    : []),
            ];
            return (
                <div
                    className="seccion"
                    style={{ display: 'flex', gap: '4.8mm' }}
                >
                    {[
                        [
                            bloque.titulo || 'Datos del cliente',
                            ejemplo.cliente,
                            '55%',
                        ],
                        ['Datos del servicio', servicio, '45%'],
                    ].map(([titulo, filas, ancho]) => (
                        <div
                            key={titulo as string}
                            style={{ width: ancho as string, minWidth: 0 }}
                        >
                            <Barra>{titulo as string}</Barra>
                            <table className="datos">
                                <tbody>
                                    {(filas as string[][]).map(([k, v]) => (
                                        <tr key={k}>
                                            <td className="e">{k}</td>
                                            <td className="v">{v}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}
                </div>
            );
        }
        case 'declaracion':
        case 'texto': {
            const contenido =
                bloque.tipo === 'texto'
                    ? bloque.texto || '[Escribe el contenido de este bloque]'
                    : bloque.texto ||
                      datos.declaracion ||
                      '[Texto de certificación]';
            return (
                <div className="seccion">
                    {bloque.titulo && <Barra>{bloque.titulo}</Barra>}
                    <p className="declaracion">{contenido}</p>
                </div>
            );
        }
        case 'tabla': {
            const columnas = datos.columnas.filter(
                (c) => (c.tipo as string) !== 'resultado',
            );
            const conColumnaResultado =
                conResultado || columnas.length < datos.columnas.length;
            return (
                <div className="seccion">
                    <Barra>{bloque.titulo || 'Equipos inspeccionados'}</Barra>
                    <table className="tabla" style={{ tableLayout: 'fixed' }}>
                        <thead>
                            <tr>
                                {columnas.map((c, i) => (
                                    <th
                                        key={i}
                                        style={{ overflowWrap: 'anywhere' }}
                                    >
                                        {c.titulo || '[Columna]'}
                                        {c.unidad ? ` (${c.unidad})` : ''}
                                    </th>
                                ))}
                                {conColumnaResultado && (
                                    <th className="centro">Resultado</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {[1, 2, 3].map((n) => (
                                <tr
                                    key={n}
                                    className={n % 2 === 0 ? 'par' : undefined}
                                >
                                    {columnas.map((c, i) => (
                                        <td
                                            key={i}
                                            className={
                                                c.tipo === 'numero'
                                                    ? 'centro'
                                                    : 'vacio'
                                            }
                                        >
                                            {c.tipo === 'numero' ? n : '—'}
                                        </td>
                                    ))}
                                    {conColumnaResultado && (
                                        <td className="centro">
                                            <span className="pastilla">
                                                OPERATIVO
                                            </span>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
        }
        case 'pruebas': {
            const mitad = Math.max(1, Math.ceil(datos.checklist.length / 2));
            const lados = [
                datos.checklist.slice(0, mitad),
                datos.checklist.slice(mitad),
            ].filter((lado) => lado.length > 0);
            return (
                <div
                    className="seccion"
                    style={{ display: 'flex', gap: '4.8mm' }}
                >
                    {lados.map((lado, l) => (
                        <div key={l} style={{ width: '50%', minWidth: 0 }}>
                            <Barra>
                                {l === 0
                                    ? bloque.titulo || 'Pruebas realizadas'
                                    : ''}
                            </Barra>
                            <table className="tabla pruebas">
                                <thead>
                                    <tr>
                                        <th>N.°</th>
                                        <th>Verificación</th>
                                        <th className="centro">C</th>
                                        <th className="centro">NC</th>
                                        <th className="centro">N/A</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lado.map((p, i) => {
                                        const numero = l * mitad + i;
                                        return (
                                            <tr
                                                key={i}
                                                className={
                                                    numero % 2 === 1
                                                        ? 'par'
                                                        : undefined
                                                }
                                            >
                                                <td className="n">
                                                    {String(
                                                        numero + 1,
                                                    ).padStart(2, '0')}
                                                </td>
                                                <td>
                                                    {p.texto || '[Prueba]'}
                                                    {p.unidad && (
                                                        <span
                                                            style={{
                                                                color: '#6b6965',
                                                            }}
                                                        >
                                                            {' '}
                                                            · — {p.unidad}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="m">
                                                    <span className="caja caja-c">
                                                        ✔
                                                    </span>
                                                </td>
                                                <td className="m">
                                                    <span className="caja" />
                                                </td>
                                                <td className="m">
                                                    <span className="caja" />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ))}
                </div>
            );
        }
        case 'resultado':
            return (
                <div className="seccion">
                    <Barra>
                        {bloque.titulo || 'Resultado de la evaluación'}
                    </Barra>
                    <table className="resultado">
                        <tbody>
                            <tr>
                                {[
                                    'OPERATIVO',
                                    'OPERATIVO CON OBSERVACIÓN',
                                    'NO OPERATIVO',
                                ].map((t, i) => (
                                    <td
                                        key={t}
                                        className={
                                            i === 0 ? 'celda elegida' : 'celda'
                                        }
                                    >
                                        <div className="opcion">
                                            <span
                                                className={
                                                    i === 0
                                                        ? 'caja caja-c'
                                                        : 'caja'
                                                }
                                            >
                                                {i === 0 ? '✔' : ''}
                                            </span>
                                            {t}
                                        </div>
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
            );
        case 'responsabilidad':
            return (
                <p className="responsabilidad">
                    {bloque.texto ||
                        datos.responsabilidad ||
                        '[Texto final y responsabilidad]'}
                </p>
            );
        case 'diploma':
            return (
                <div style={{ marginTop: '8mm', textAlign: 'center' }}>
                    <div
                        style={{
                            fontFamily: 'DinCond',
                            fontSize: '11pt',
                            letterSpacing: '1.4pt',
                            color: '#2e2d2c',
                        }}
                    >
                        OTORGADO A
                    </div>
                    <div
                        style={{
                            margin: '3mm 12mm 0',
                            paddingBottom: '1.5mm',
                            borderBottom: '1.1pt solid #d20404',
                            fontFamily: 'DinSubtitulo',
                            fontSize: '20pt',
                            lineHeight: 1.1,
                        }}
                    >
                        [NOMBRE DEL PARTICIPANTE]
                    </div>
                    <div
                        style={{
                            marginTop: '2mm',
                            fontSize: '10.5pt',
                            color: '#3f3d3b',
                        }}
                    >
                        {bloque.texto ||
                            datos.declaracion ||
                            'Por haber participado satisfactoriamente en la capacitación de'}{' '}
                        en:
                    </div>
                    <div
                        style={{
                            margin: '3mm 0 1mm',
                            fontFamily: 'DinSubtitulo',
                            fontSize: '17pt',
                            color: '#d20404',
                            lineHeight: 1.1,
                        }}
                    >
                        [CURSO DE CAPACITACIÓN]
                    </div>
                    <div
                        style={{
                            marginTop: '2mm',
                            fontSize: '10.5pt',
                            color: '#3f3d3b',
                        }}
                    >
                        Con una duración de [horas] horas, realizada en Trujillo
                        el [fecha del curso].
                    </div>
                </div>
            );
    }
}

function Firma({ firma }: { firma: Firmante }) {
    return (
        <>
            {firma.firma_url ? (
                <img
                    className="firma-img"
                    src={firma.firma_url}
                    alt={`Firma de ${firma.nombre}`}
                    style={{ margin: '2mm auto 0' }}
                />
            ) : (
                <div className="firma-vacia" />
            )}
            <div className="firma-linea">
                <div className="firma-nombre">{firma.nombre}</div>
                <div className="firma-cargo">{firma.cargo}</div>
                {firma.cip && (
                    <div className="firma-cargo">CIP N.° {firma.cip}</div>
                )}
            </div>
        </>
    );
}

function Firmas({ firmantes }: { firmantes: Firmante[] }) {
    const posicionSello = Math.floor(firmantes.length / 2);
    const celdaSello = (
        <td style={{ width: '26mm', verticalAlign: 'middle' }}>
            <img
                src={sello}
                alt="Sello de Bruce Fire"
                style={{ width: '21mm' }}
            />
        </td>
    );
    return (
        <table className="firmas">
            <tbody>
                <tr>
                    <td
                        style={{
                            width: '38mm',
                            verticalAlign: 'bottom',
                            paddingLeft: 0,
                        }}
                    >
                        <Asneex />
                    </td>
                    {firmantes.map((f, i) => (
                        <FirmaConSello
                            key={i}
                            antes={i === posicionSello ? celdaSello : null}
                        >
                            <Firma firma={f} />
                        </FirmaConSello>
                    ))}
                    {posicionSello >= firmantes.length && celdaSello}
                    {firmantes.length === 0 && (
                        <td
                            style={{
                                verticalAlign: 'middle',
                                color: '#6b6965',
                            }}
                        >
                            [Elige quién firma]
                        </td>
                    )}
                </tr>
            </tbody>
        </table>
    );
}

function FirmaConSello({
    antes,
    children,
}: {
    antes: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            {antes}
            <td>{children}</td>
        </>
    );
}

/** Hoja vertical u horizontal de certificado-operatividad.blade.php. */
function HojaOperatividad({
    datos,
    horizontal,
}: {
    datos: Datos;
    horizontal: boolean;
}) {
    const [linea1, linea2] = (datos.titulo || 'CERTIFICADO')
        .toUpperCase()
        .split(/ (.*)/s);
    const sub = datos.subtitulo.toUpperCase();
    return (
        <>
            <img
                src={marcaAgua}
                alt=""
                style={{
                    position: 'absolute',
                    top: horizontal ? '62mm' : '108mm',
                    left: horizontal ? '99mm' : '55mm',
                    width: '100mm',
                }}
            />
            <div style={{ position: 'relative' }}>
                <table>
                    <tbody>
                        <tr>
                            <td style={{ verticalAlign: 'middle' }}>
                                <img
                                    src={logo}
                                    alt="Extintores Bruce Fire"
                                    style={{ width: '112mm' }}
                                />
                            </td>
                            <td
                                style={{
                                    width: '62mm',
                                    paddingLeft: '4mm',
                                    verticalAlign: 'middle',
                                }}
                            >
                                <div
                                    style={{
                                        border: '1.1pt solid #d20404',
                                        borderRadius: '4pt',
                                        padding: '.5mm 2.4mm',
                                        fontFamily: 'DinCond',
                                        fontWeight: 700,
                                        fontSize: '11.5pt',
                                        color: '#d20404',
                                        textAlign: 'center',
                                    }}
                                >
                                    RUC:{' '}
                                    <span
                                        className="mono"
                                        style={{
                                            color: '#1b1918',
                                            letterSpacing: '.6pt',
                                        }}
                                    >
                                        20616376528
                                    </span>
                                </div>
                                <div
                                    style={{
                                        marginTop: '1mm',
                                        paddingLeft: '1.5mm',
                                    }}
                                >
                                    {ejemplo.telefonos.map((t) => (
                                        <div
                                            key={t}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '2.2mm',
                                            }}
                                        >
                                            <img
                                                src={iconoWhatsapp}
                                                alt=""
                                                style={{ width: '3.8mm' }}
                                            />
                                            <span
                                                className="mono"
                                                style={{
                                                    fontSize: '9.8pt',
                                                    letterSpacing: '.2pt',
                                                }}
                                            >
                                                {t}
                                            </span>
                                        </div>
                                    ))}
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '2.2mm',
                                        }}
                                    >
                                        <img
                                            src={iconoGmail}
                                            alt=""
                                            style={{ width: '3.8mm' }}
                                        />
                                        <span
                                            style={{
                                                fontFamily: 'DinCond',
                                                fontSize: '8.2pt',
                                            }}
                                        >
                                            {ejemplo.correo}
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        marginTop: '1.6mm',
                        gap: '3mm',
                    }}
                >
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <Ajustado
                            maximo={40}
                            style={{
                                fontFamily: 'DinTitulo',
                                lineHeight: 0.96,
                                maxWidth: '112mm',
                            }}
                        >
                            {linea1}
                        </Ajustado>
                        {linea2 && (
                            <Ajustado
                                maximo={27}
                                style={{
                                    fontFamily: 'DinTitulo',
                                    lineHeight: 0.96,
                                    maxWidth: '112mm',
                                }}
                            >
                                {linea2}
                            </Ajustado>
                        )}
                        {sub && (
                            <Ajustado
                                maximo={19}
                                style={{
                                    fontFamily: 'DinSubtitulo',
                                    color: '#d20404',
                                    lineHeight: 1.02,
                                    marginTop: '.4mm',
                                    maxWidth: '112mm',
                                }}
                            >
                                {sub}
                            </Ajustado>
                        )}
                    </div>
                    <Tarjeta datos={datos} ancho="66mm" />
                </div>
                {datos.norma && (
                    <div
                        style={{
                            marginTop: '1.2mm',
                            fontSize: '8.8pt',
                            color: '#3f3d3b',
                            textAlign: 'center',
                        }}
                    >
                        {datos.norma}
                    </div>
                )}

                {datos.composicion.map((b, i) => (
                    <Bloque key={i} bloque={b} datos={datos} />
                ))}

                <Firmas firmantes={datos.firmantes} />
            </div>
        </>
    );
}

/** Diploma horizontal de certificado-diploma.blade.php (posiciones absolutas en mm). */
function HojaDiploma({ datos }: { datos: Datos }) {
    const diploma = datos.composicion.find((b) => b.tipo === 'diploma');
    const instructor = datos.firmantes[0];
    const capa = (top: string, extra: CSSProperties = {}): CSSProperties => ({
        position: 'absolute',
        top,
        left: '30mm',
        right: '30mm',
        textAlign: 'center',
        ...extra,
    });
    return (
        <>
            <img
                src={marcaAgua}
                alt=""
                style={{
                    position: 'absolute',
                    top: '42mm',
                    left: '95mm',
                    width: '107mm',
                }}
            />
            <div
                style={{
                    position: 'absolute',
                    top: '9mm',
                    right: '10mm',
                    width: '42mm',
                }}
            >
                <Asneex />
            </div>
            <div style={capa('11mm')}>
                <img
                    src={logo}
                    alt="Extintores Bruce Fire"
                    style={{ width: '150mm' }}
                />
            </div>
            <div style={capa('47mm')}>
                <Ajustado
                    maximo={55}
                    style={{
                        fontFamily: 'DinTitulo',
                        lineHeight: 1.1,
                        letterSpacing: '.4pt',
                    }}
                >
                    {(datos.titulo || 'CERTIFICADO').toUpperCase()}
                </Ajustado>
            </div>
            <div
                style={capa('71mm', {
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                })}
            >
                <div
                    style={{ width: '42mm', borderTop: '.9pt solid #2e2d2c' }}
                />
                <div
                    style={{
                        width: '1.8mm',
                        height: '1.8mm',
                        background: '#d20404',
                    }}
                />
                <div
                    style={{
                        color: '#d20404',
                        fontFamily: 'DinCond',
                        fontWeight: 700,
                        fontSize: '16pt',
                        letterSpacing: '6pt',
                        lineHeight: 1,
                        whiteSpace: 'nowrap',
                        padding: '0 2.9mm 0 5mm',
                    }}
                >
                    {(datos.subtitulo || 'DE CAPACITACIÓN').toUpperCase()}
                </div>
                <div
                    style={{
                        width: '1.8mm',
                        height: '1.8mm',
                        background: '#d20404',
                    }}
                />
                <div
                    style={{ width: '42mm', borderTop: '.9pt solid #2e2d2c' }}
                />
            </div>
            <div
                style={capa('88mm', {
                    fontFamily: 'DinCond',
                    fontSize: '12pt',
                    letterSpacing: '1.4pt',
                    color: '#2e2d2c',
                })}
            >
                OTORGADO A
            </div>
            <div style={capa('101mm')}>
                <Ajustado
                    maximo={30}
                    style={{ fontFamily: 'DinSubtitulo', lineHeight: 1.1 }}
                >
                    [NOMBRE DEL PARTICIPANTE]
                </Ajustado>
            </div>
            <div
                style={{
                    position: 'absolute',
                    top: '115.5mm',
                    left: '51mm',
                    right: '51mm',
                    borderTop: '1.1pt solid #d20404',
                }}
            />
            <div
                style={capa('121.5mm', {
                    left: '25mm',
                    right: '25mm',
                    fontSize: '11.8pt',
                    lineHeight: 1.35,
                    color: '#3f3d3b',
                })}
            >
                {diploma?.texto ||
                    datos.declaracion ||
                    'Por haber participado satisfactoriamente en la capacitación de'}{' '}
                en:
            </div>
            <div
                style={capa('132mm', {
                    fontFamily: 'DinSubtitulo',
                    fontSize: '26pt',
                    color: '#d20404',
                    lineHeight: 1.1,
                    whiteSpace: 'nowrap',
                })}
            >
                [CURSO DE CAPACITACIÓN]
            </div>
            <div
                style={capa('146mm', { fontSize: '11.8pt', color: '#3f3d3b' })}
            >
                Con una duración de{' '}
                <b style={{ color: '#d20404' }}>[horas] horas</b>, realizada en
                Trujillo el [fecha del curso].
            </div>
            {datos.responsabilidad && (
                <div style={capa('153mm', { fontSize: '8pt' })}>
                    {datos.responsabilidad}
                </div>
            )}
            <div
                style={{
                    position: 'absolute',
                    top: '162mm',
                    left: '10mm',
                    width: '86mm',
                }}
            >
                <Tarjeta datos={datos} ancho="100%" />
                <div
                    className="etiqueta"
                    style={{ marginTop: '1.2mm', textAlign: 'center' }}
                >
                    ESCANEE PARA VERIFICAR SU AUTENTICIDAD
                </div>
            </div>
            <img
                src={sello}
                alt="Sello de Bruce Fire"
                style={{
                    position: 'absolute',
                    top: '155mm',
                    left: '128mm',
                    width: '36mm',
                }}
            />
            <div
                style={{
                    position: 'absolute',
                    top: '164mm',
                    left: '194mm',
                    width: '65mm',
                    textAlign: 'center',
                }}
            >
                {instructor?.firma_url ? (
                    <img
                        src={instructor.firma_url}
                        alt={`Firma de ${instructor.nombre}`}
                        style={{ height: '14mm', margin: '0 auto' }}
                    />
                ) : (
                    <div style={{ height: '14mm' }} />
                )}
                <div
                    style={{
                        borderTop: '.8pt solid #1b1918',
                        paddingTop: '1.2mm',
                    }}
                >
                    <div
                        style={{
                            fontFamily: 'DinCond',
                            fontWeight: 700,
                            fontSize: '11pt',
                        }}
                    >
                        {instructor?.nombre ?? '[Elige quién firma]'}
                    </div>
                    <div
                        style={{
                            fontFamily: 'DinCond',
                            fontSize: '8.4pt',
                            color: '#d20404',
                            letterSpacing: '1pt',
                            textTransform: 'uppercase',
                        }}
                    >
                        {instructor?.cargo ?? 'Instructor'}
                    </div>
                </div>
            </div>
        </>
    );
}

/** Marco protegido; el contenido sigue el orden de la composición guardada. */
export default function HojaDinamicaCertificado({ datos }: { datos: Datos }) {
    const contenedor = useRef<HTMLDivElement>(null);
    const papel = useRef<HTMLDivElement>(null);
    const [anchoDisponible, setAnchoDisponible] = useState(600);
    const [altoContenido, setAltoContenido] = useState(0);
    const horizontal = datos.orientacion === 'horizontal';
    const diploma =
        horizontal && datos.composicion.some((b) => b.tipo === 'diploma');
    // A4 a 96 ppp.
    const ancho = horizontal ? 1123 : 794;
    const alto = horizontal ? 794 : 1123;
    const escala = Math.min(1, anchoDisponible / ancho);
    useEffect(() => {
        const element = contenedor.current;
        if (!element) return;
        const observer = new ResizeObserver(([entry]) =>
            setAnchoDisponible(entry.contentRect.width),
        );
        observer.observe(element);
        return () => observer.disconnect();
    }, []);
    useEffect(() => {
        const element = papel.current;
        if (!element) return;
        const observer = new ResizeObserver(([entry]) =>
            setAltoContenido(
                entry.borderBoxSize[0]?.blockSize ?? element.offsetHeight,
            ),
        );
        observer.observe(element);
        return () => observer.disconnect();
    }, []);
    return (
        <div
            ref={contenedor}
            className="w-full"
            aria-label="Vista previa del certificado con datos de ejemplo"
        >
            <style>{estilos}</style>
            <div
                style={{
                    height: Math.max(alto, altoContenido) * escala,
                    position: 'relative',
                }}
            >
                <div
                    ref={papel}
                    className="hd"
                    style={{
                        position: 'absolute',
                        left: '50%',
                        width: ancho,
                        minHeight: alto,
                        height: diploma ? alto : undefined,
                        overflow: 'hidden',
                        transform: `translateX(-50%) scale(${escala})`,
                        transformOrigin: 'top center',
                        background: '#fff',
                        padding: diploma ? 0 : '10mm 13mm 14mm',
                        boxShadow: '0 1px 6px #0002',
                    }}
                >
                    <img
                        src={franjaSuperior}
                        alt=""
                        style={{
                            position: 'absolute',
                            top: 0,
                            left: 0,
                            width: diploma ? '61mm' : '56mm',
                        }}
                    />
                    <img
                        src={franjaInferior}
                        alt=""
                        style={{
                            position: 'absolute',
                            bottom: 0,
                            right: 0,
                            width: diploma ? '61mm' : '56mm',
                        }}
                    />
                    {diploma ? (
                        <HojaDiploma datos={datos} />
                    ) : (
                        <HojaOperatividad
                            datos={datos}
                            horizontal={horizontal}
                        />
                    )}
                    {!diploma && (
                        <div
                            style={{
                                position: 'absolute',
                                left: '14mm',
                                right: '14mm',
                                bottom: '7mm',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '3.5mm',
                                fontFamily: 'DinCond',
                                fontSize: '7.4pt',
                                color: '#5c5a58',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            <div
                                style={{
                                    flex: 1,
                                    borderTop: '.6pt solid #d20404',
                                }}
                            />
                            BRUCE FIRE S.A.C. · RUC 20616376528 · Trujillo ·
                            Página 1 de 1
                            <div
                                style={{
                                    flex: 1,
                                    borderTop: '.6pt solid #d20404',
                                }}
                            />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
