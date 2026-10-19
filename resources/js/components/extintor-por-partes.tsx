import type { ReactNode } from 'react';

export type TipoExtintor = 'pqs' | 'co2' | 'acetato' | 'rodante' | 'otro';

export type ParteExtintor = {
    clave: string;
    nombre: string;
    opciones: string[];
};

const VALVULA: ParteExtintor = {
    clave: 'valvula',
    nombre: 'Válvula',
    opciones: ['Fuga', 'Palanca rota', 'Sin pasador', 'Trabada', 'Otro'],
};
const MANOMETRO: ParteExtintor = {
    clave: 'manometro',
    nombre: 'Manómetro',
    opciones: ['Aguja en rojo', 'Vidrio roto', 'No marca', 'Otro'],
};
const MANGUERA: ParteExtintor = {
    clave: 'manguera',
    nombre: 'Manguera',
    opciones: ['Rota', 'Reseca', 'Tapada', 'Falta', 'Otro'],
};
const CILINDRO: ParteExtintor = {
    clave: 'cilindro',
    nombre: 'Cilindro',
    opciones: ['Óxido', 'Golpe', 'Pintura', 'Fuga', 'Otro'],
};
const AGENTE: ParteExtintor = {
    clave: 'agente_carga',
    nombre: 'Agente extintor',
    opciones: ['Compactado', 'Bajo peso', 'Húmedo', 'Contaminado', 'Otro'],
};
const RUEDAS: ParteExtintor = {
    clave: 'ruedas',
    nombre: 'Ruedas y carro',
    opciones: ['Rueda rota', 'Eje trabado', 'Carro doblado', 'Otro'],
};

/** Las partes que se revisan en planta, según el tipo de extintor. */
export const PARTES: Record<TipoExtintor, ParteExtintor[]> = {
    pqs: [MANOMETRO, VALVULA, MANGUERA, CILINDRO, AGENTE],
    acetato: [MANOMETRO, VALVULA, MANGUERA, CILINDRO, AGENTE],
    co2: [VALVULA, MANGUERA, CILINDRO],
    rodante: [CILINDRO, MANGUERA, VALVULA, MANOMETRO, RUEDAS],
    otro: [CILINDRO, MANGUERA, VALVULA, MANOMETRO],
};

/** Qué dibujo usar: por el agente y, desde 25 kg, rodante. */
export function tipoDeExtintor(
    agente: string | null,
    capacidad: string | null,
): TipoExtintor {
    const texto = `${agente ?? ''}`.toUpperCase();
    const kilos = Number.parseFloat(`${capacidad ?? ''}`.replace(',', '.'));
    const enKilos = /KG/i.test(capacidad ?? '');

    if (/RODANTE/i.test(`${agente} ${capacidad}`) || (enKilos && kilos >= 25)) {
        return 'rodante';
    }

    if (texto.includes('CO2')) {
        return 'co2';
    }

    if (texto.includes('ACETATO') || texto.includes('(K)')) {
        return 'acetato';
    }

    return texto.includes('PQS') ? 'pqs' : 'otro';
}

const COLOR = {
    bien: { fondo: '#BBF7D0', borde: '#15803D' },
    mal: { fondo: '#FCA5A5', borde: '#B91C1C' },
    fijo: { fondo: '#D6D3D1', borde: '#44403C' },
};

/**
 * El extintor dibujado por partes (redibujado en vector desde la referencia
 * de GPT): verde lo que está bien, rojo lo que tiene falla. Cada parte se
 * toca para marcarla; la misma acción está en botones debajo del dibujo.
 */
export default function ExtintorPorPartes({
    tipo,
    malas,
    onTocar,
}: {
    tipo: TipoExtintor;
    malas: Record<string, boolean>;
    onTocar: (clave: string) => void;
}) {
    const color = (clave: string) => (malas[clave] ? COLOR.mal : COLOR.bien);
    const parte = (clave: string, hijos: ReactNode) => (
        <g
            onClick={() => onTocar(clave)}
            className="cursor-pointer"
            fill={color(clave).fondo}
            stroke={color(clave).borde}
            strokeWidth={10}
            strokeLinejoin="round"
        >
            {hijos}
        </g>
    );
    const rodante = tipo === 'rodante';
    const co2 = tipo === 'co2';
    const conManometro = !co2;
    const ventana = tipo === 'pqs' || tipo === 'acetato';

    return (
        <svg
            viewBox="0 0 1024 1536"
            className="mx-auto h-auto w-full max-w-[300px]"
            aria-hidden="true"
        >
            {rodante ? (
                <g
                    fill="none"
                    stroke={COLOR.fijo.borde}
                    strokeWidth={26}
                    strokeLinecap="round"
                >
                    <path d="M262 1330 L262 80 Q262 32 310 32 L612 32 Q660 32 660 80 L660 1330" />
                </g>
            ) : null}

            {/* Cilindro */}
            {parte(
                'cilindro',
                co2 ? (
                    <>
                        <path d="M297 410 Q297 300 446 300 Q595 300 595 410 L595 1290 L297 1290 Z" />
                        <rect
                            x="292"
                            y="1280"
                            width="308"
                            height="112"
                            rx="24"
                        />
                    </>
                ) : rodante ? (
                    <>
                        <path d="M268 430 Q268 330 456 330 Q644 330 644 430 L644 1250 Q644 1300 596 1300 L316 1300 Q268 1300 268 1250 Z" />
                        <path d="M268 1200 L644 1200" fill="none" />
                    </>
                ) : (
                    <>
                        <path d="M258 440 Q258 318 460 318 Q662 318 662 440 L662 1370 L258 1370 Z" />
                        <rect
                            x="252"
                            y="1360"
                            width="416"
                            height="110"
                            rx="26"
                        />
                    </>
                ),
            )}

            {/* Agente: la ventana que deja ver el polvo o el líquido */}
            {ventana
                ? parte(
                      'agente_carga',
                      <>
                          <rect
                              x="292"
                              y="618"
                              width="333"
                              height="645"
                              rx="80"
                          />
                          <path
                              d={
                                  tipo === 'pqs'
                                      ? 'M300 800 Q360 770 420 790 Q500 815 560 785 Q600 770 618 790'
                                      : 'M300 795 L618 790'
                              }
                              fill="none"
                          />
                      </>,
                  )
                : null}

            {/* Manguera */}
            {parte(
                'manguera',
                co2 ? (
                    <>
                        <path
                            d="M560 205 Q760 200 800 380 Q825 500 818 610"
                            fill="none"
                            strokeWidth={60}
                            stroke={color('manguera').borde}
                        />
                        <rect x="762" y="598" width="70" height="46" rx="6" />
                        <path d="M770 644 L822 644 L905 1035 L697 1035 Z" />
                    </>
                ) : tipo === 'acetato' ? (
                    <>
                        <path
                            d="M632 216 Q790 200 820 360 Q835 450 812 560"
                            fill="none"
                            strokeWidth={54}
                            stroke={color('manguera').borde}
                        />
                        <rect x="780" y="560" width="62" height="50" rx="6" />
                        <rect x="783" y="640" width="56" height="150" rx="12" />
                        <rect x="797" y="790" width="28" height="440" rx="6" />
                        <path d="M792 1230 L830 1230 L838 1290 L784 1290 Z" />
                    </>
                ) : rodante ? (
                    <>
                        <ellipse
                            cx="740"
                            cy="660"
                            rx="64"
                            ry="190"
                            fill="none"
                            strokeWidth={30}
                        />
                        <ellipse
                            cx="770"
                            cy="660"
                            rx="64"
                            ry="190"
                            fill="none"
                            strokeWidth={30}
                        />
                        <path
                            d="M600 236 Q760 236 800 420 Q830 560 862 780"
                            fill="none"
                            strokeWidth={44}
                            stroke={color('manguera').borde}
                        />
                        <rect x="842" y="770" width="46" height="60" rx="6" />
                        <rect x="846" y="830" width="38" height="320" rx="8" />
                    </>
                ) : (
                    <>
                        <path
                            d="M632 216 Q790 210 830 330 Q860 450 820 700 L810 818"
                            fill="none"
                            strokeWidth={58}
                            stroke={color('manguera').borde}
                        />
                        <rect x="765" y="815" width="74" height="56" rx="6" />
                        <path d="M772 872 L840 872 L870 1125 L705 1125 Z" />
                    </>
                ),
            )}

            {/* Válvula: palanca, manija, cuerpo y collar */}
            {parte(
                'valvula',
                <>
                    <path d="M210 52 Q198 38 216 33 L232 32 Q372 60 490 110 Q502 122 488 134 L470 140 Q350 96 222 72 Q205 68 210 52 Z" />
                    <path d="M160 250 Q147 238 166 224 Q282 165 395 148 L402 180 Q300 198 186 254 Q170 260 160 250 Z" />
                    <rect x="395" y="118" width="95" height="150" rx="8" />
                    <rect x="388" y="262" width="125" height="62" rx="6" />
                    <rect
                        x={co2 ? 488 : 560}
                        y={co2 ? 172 : 186}
                        width="72"
                        height="62"
                        rx="6"
                    />
                </>,
            )}

            {/* Manómetro */}
            {conManometro
                ? parte(
                      'manometro',
                      <>
                          <circle cx="515" cy="228" r="56" />
                          <circle cx="515" cy="228" r="38" fill="#FFFFFF" />
                          <path
                              d="M515 228 L540 200"
                              fill="none"
                              strokeWidth={9}
                              strokeLinecap="round"
                          />
                      </>,
                  )
                : null}

            {/* Ruedas (solo el rodante) */}
            {rodante
                ? parte(
                      'ruedas',
                      <>
                          <rect
                              x="190"
                              y="1392"
                              width="540"
                              height="34"
                              rx="12"
                          />
                          <circle cx="190" cy="1410" r="90" />
                          <circle cx="190" cy="1410" r="38" fill="#FFFFFF" />
                          <circle cx="740" cy="1410" r="90" />
                          <circle cx="740" cy="1410" r="38" fill="#FFFFFF" />
                      </>,
                  )
                : null}
        </svg>
    );
}
