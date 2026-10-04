{{--
    Certificado estilo C (maqueta aprobada por Bruce Fire): franjas en las esquinas,
    título itálico grueso, barras de sección grafito con punta roja, QR con estado,
    checklist, resultado global, firmas con sello y marca de agua.
    Todo lo que cambia entre servicios llega en $tipo (columnas, checklist, bloques,
    textos) y los datos del certificado en $certificado, $cliente, $filas, $pruebas.
--}}
@php
    $columnas = $tipo['columnas'] ?? [];
    $claves = array_column($columnas, 'clave');
    $tituloTabla = in_array('componente', $claves, true) ? 'Componentes inspeccionados'
        : (in_array('ambiente', $claves, true) ? 'Instalación realizada'
        : (in_array('serie', $claves, true) ? 'Información técnica de los extintores' : 'Equipos inspeccionados'));
    $centradas = ['item', 'resultado', 'anio', 'capacidad', 'potencia', 'bateria', 'autonomia', 'presion_ph', 'tiempo_ph', 'proxima_ph', 'vencimiento', 'espesor', 'metros'];
    $estado = $certificado['estado'];
    $posicionSello = intdiv(count($firmantes), 2);
    $mitadPruebas = max(1, (int) ceil(count($pruebas) / 2));
    $bloques = $tipo['bloques'] ?? [];
    $conAsneex = in_array('asneex', $tipo['logos'] ?? [], true);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificado['numero'] }}</title>
    <style>
        @font-face { font-family: 'Titulo'; font-weight: normal; font-style: normal; src: url('{{ $recursos['fuentes']['titulo'] }}') format('truetype'); }
        @font-face { font-family: 'Subtitulo'; font-weight: normal; font-style: normal; src: url('{{ $recursos['fuentes']['subtitulo'] }}') format('truetype'); }
        @font-face { font-family: 'Cond'; font-weight: normal; font-style: normal; src: url('{{ $recursos['fuentes']['cond'] }}') format('truetype'); }
        @font-face { font-family: 'Cond'; font-weight: bold; font-style: normal; src: url('{{ $recursos['fuentes']['cond_negrita'] }}') format('truetype'); }
        @font-face { font-family: 'Texto'; font-weight: normal; font-style: normal; src: url('{{ $recursos['fuentes']['texto'] }}') format('truetype'); }
        @font-face { font-family: 'Texto'; font-weight: bold; font-style: normal; src: url('{{ $recursos['fuentes']['texto_negrita'] }}') format('truetype'); }
        @font-face { font-family: 'Mono'; font-weight: normal; font-style: normal; src: url('{{ $recursos['fuentes']['mono'] }}') format('truetype'); }
        @font-face { font-family: 'Mono'; font-weight: bold; font-style: normal; src: url('{{ $recursos['fuentes']['mono_negrita'] }}') format('truetype'); }

        @page { margin: 10mm 13mm 14mm 13mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Texto', sans-serif; font-size: 8.4pt; color: #1b1918; line-height: 1.28; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 0; vertical-align: top; }
        .check { font-family: 'DejaVu Sans', sans-serif; }
        .mono { font-family: 'Mono', monospace; }

        /* Decoración fija en todas las hojas */
        .franja-sup { position: fixed; top: -10mm; left: -13mm; width: 56mm; }
        .franja-inf { position: fixed; bottom: -14mm; right: -13mm; width: 56mm; }
        .marca-agua { position: fixed; top: 98mm; left: 42mm; width: 100mm; }
        .anulado { position: fixed; top: 128mm; left: -10mm; width: 204mm; text-align: center; font-family: 'Titulo'; font-size: 78pt; color: #f2c4c4; transform: rotate(-32deg); }

        /* Cabecera */
        .cabecera td { vertical-align: middle; }
        .logo { width: 112mm; }
        .ruc { border: 1.1pt solid #d20404; border-radius: 4pt; padding: 0.5mm 2.4mm; font-family: 'Cond'; font-weight: bold; font-size: 11.5pt; color: #d20404; text-align: center; }
        .ruc .mono { color: #1b1918; font-size: 11.5pt; letter-spacing: 0.6pt; }
        .contacto { margin-top: 1mm; padding-left: 1.5mm; }
        .contacto td { vertical-align: middle; padding: 0.1mm 0; }
        .contacto img { width: 3.8mm; height: 3.8mm; }
        .contacto .icono { width: 6mm; }
        .contacto .telefono { font-family: 'Mono'; font-weight: bold; font-size: 9.8pt; letter-spacing: 0.2pt; }
        .contacto .correo { font-family: 'Cond'; font-size: 8.2pt; }

        /* Título y verificación */
        .titulo { margin-top: 1.6mm; }
        .titulo td { vertical-align: middle; }
        .t1, .t2 { font-family: 'Titulo'; color: #1b1918; line-height: 0.96; white-space: nowrap; }
        .t3 { font-family: 'Subtitulo'; color: #d20404; line-height: 1.02; margin-top: 0.4mm; white-space: nowrap; }
        .norma { margin-top: 1.2mm; font-size: 8.8pt; color: #3f3d3b; text-align: center; }
        .verifica { width: 66mm; }
        .tarjeta { border: 0.8pt solid #cfcfcf; border-radius: 6pt; padding: 1.8mm; }
        .tarjeta td { vertical-align: middle; }
        .qr { width: 23mm; height: 23mm; }
        .numero { font-family: 'Mono'; font-weight: bold; font-size: 11.5pt; letter-spacing: 0.2pt; white-space: nowrap; }
        .etiqueta { font-family: 'Cond'; font-weight: bold; font-size: 6.8pt; color: #6b6965; letter-spacing: 0.8pt; }
        .estado { margin-top: 1.4mm; border-radius: 4pt; padding: 1.1mm 1.6mm; text-align: center; color: #ffffff; }
        .estado .texto { font-family: 'Cond'; font-weight: bold; font-size: 7.6pt; letter-spacing: 0.6pt; }
        .estado .fecha { font-family: 'Cond'; font-weight: bold; font-size: 14pt; line-height: 1.05; }
        .estado-vigente { background: #0e7a3e; }
        .estado-vencido { background: #b45309; }
        .estado-anulado, .estado-reemplazado { background: #b91c1c; }
        .revision { margin-top: 0.8mm; font-size: 6.6pt; color: #6b6965; text-align: center; }

        /* Barras de sección */
        .seccion { margin-top: 2.2mm; }
        .barra td { vertical-align: middle; }
        .barra .texto { background: #2e2d2c; border-left: 1.3mm solid #d20404; color: #ffffff; font-family: 'Cond'; font-weight: bold; font-size: 9.8pt; letter-spacing: 0.5pt; text-transform: uppercase; padding: 0.85mm 2.6mm; height: 5.8mm; }
        .barra .cola { width: 5.4mm; }
        .barra .cola img { width: 5.4mm; height: 5.8mm; display: block; }

        /* Bloques de datos */
        .datos { border: 0.6pt solid #d9d9d9; border-top: 0; }
        .datos td { border-bottom: 0.6pt solid #e6e6e6; padding: 0.6mm 2.4mm; vertical-align: middle; }
        .datos .dato-etiqueta { width: 32mm; background: #f6f5f4; color: #d20404; font-family: 'Cond'; font-weight: bold; font-size: 8.6pt; border-right: 0.6pt solid #e6e6e6; }
        .datos .dato-valor { font-family: 'Cond'; font-weight: bold; font-size: 8.8pt; color: #1b1918; }
        .declaracion { margin: 2mm 0 0; font-size: 8.5pt; line-height: 1.3; text-align: justify; }
        .declaracion b { font-family: 'Texto'; font-weight: bold; }

        /* Tablas */
        .tabla { border: 0.6pt solid #d9d9d9; border-top: 0; }
        .tabla th { background: #f1f0ef; color: #1b1918; font-family: 'Cond'; font-weight: bold; font-size: 8.4pt; padding: 0.7mm 1.6mm; text-align: left; border-bottom: 0.6pt solid #d9d9d9; border-right: 0.6pt solid #e4e4e4; }
        .tabla td { padding: 0.5mm 1.6mm; font-family: 'Cond'; font-size: 8.6pt; border-bottom: 0.6pt solid #e8e8e8; border-right: 0.6pt solid #eeeeee; vertical-align: middle; }
        .tabla tr.par td { background: #fbfaf9; }
        .tabla .centro { text-align: center; }
        .compacta td { padding-top: 0.2mm; padding-bottom: 0.2mm; font-size: 8.2pt; }
        .compacta .pastilla { font-size: 6.6pt; padding: 0.1mm 1.6mm; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .pastilla { padding: 0.35mm 2mm; border-radius: 2.5pt; font-family: 'Cond'; font-weight: bold; font-size: 7.2pt; letter-spacing: 0.3pt; color: #ffffff; }
        .p-operativo { background: #0e7a3e; }
        .p-observado { background: #d97706; }
        .p-no_operativo { background: #b91c1c; }

        /* Checklist */
        .pruebas th { font-size: 7.4pt; letter-spacing: 0.4pt; text-transform: uppercase; padding: 0.6mm 1.2mm; }
        .pruebas td { font-size: 7.9pt; padding: 0.35mm 1.2mm; }
        .pruebas .n { width: 6.5mm; color: #d20404; font-family: 'Cond'; font-weight: bold; }
        .pruebas .col-marca { width: 6.4mm; text-align: center; padding: 0.35mm 0; }
        .valor { color: #6b6965; }
        .caja { display: inline-block; width: 3.2mm; height: 3.2mm; border: 0.8pt solid #9c9a98; border-radius: 0.8pt; text-align: center; line-height: 3mm; font-size: 7pt; }
        .caja-c { border-color: #0e7a3e; color: #ffffff; background: #0e7a3e; }
        .caja-nc { border-color: #b91c1c; color: #ffffff; background: #b91c1c; }

        /* Bloques técnicos */
        .bloques { border-collapse: separate; border-spacing: 1.6mm 0; margin-left: -1.6mm; width: 187.2mm; }
        .bloques td { vertical-align: top; }
        .bloque { border: 0.6pt solid #d9d9d9; border-top: 1.4pt solid #d20404; padding: 1.5mm 2mm; background: #ffffff; }
        .bloque-titulo { font-family: 'Cond'; font-weight: bold; font-size: 8pt; color: #d20404; text-transform: uppercase; }
        .bloque-texto { margin-top: 0.6mm; font-size: 7.3pt; line-height: 1.3; color: #3f3d3b; }

        /* Resultado */
        .resultado { border: 0.6pt solid #d9d9d9; border-top: 0; }
        .resultado .celda { width: 33.3%; padding: 1.4mm 2.4mm; vertical-align: middle; border-right: 0.6pt solid #e4e4e4; background: #f7f7f6; }
        .opcion td { vertical-align: middle; padding: 0; border: 0; background: transparent; }
        .opcion .marca { width: 7.4mm; }
        .opcion .texto { font-family: 'Cond'; font-weight: bold; font-size: 10.4pt; color: #5c5a58; line-height: 1.1; }
        .opcion .caja { width: 5mm; height: 5mm; line-height: 4.6mm; font-size: 10pt; border-width: 1pt; }
        .resultado .celda.elegida { background: #e7f5ec; }
        .elegida .texto { color: #0b5d30; }
        .observaciones { margin-top: 1.8mm; padding: 1.5mm 2.4mm; border-left: 2pt solid #d97706; background: #fff8eb; font-size: 8.2pt; }
        .responsabilidad { margin: 1.5mm 0 0; font-size: 7.1pt; color: #5c5a58; text-align: justify; line-height: 1.32; }

        /* Firmas */
        .firmas { margin-top: 1.5mm; page-break-inside: avoid; }
        .firmas td { text-align: center; vertical-align: top; padding: 0 2.5mm; }
        .firmas .sello-celda { vertical-align: middle; }
        .firma-img { height: 10mm; margin-top: 2mm; }
        .firma-vacia { height: 12mm; }
        .firma-linea { border-top: 0.8pt solid #1b1918; padding-top: 0.9mm; }
        .firma-nombre { font-family: 'Cond'; font-weight: bold; font-size: 9pt; }
        .firma-cargo { font-family: 'Cond'; font-size: 7.4pt; color: #3f3d3b; text-transform: uppercase; letter-spacing: 0.3pt; line-height: 1.2; }
        .sello-celda { width: 26mm; }
        .sello { width: 21mm; }
        /* Marca de agua de un certificado anulado o vencido */
        .sello-estado { position: fixed; top: 360px; left: 0; right: 0; text-align: center; font-size: 110px; font-weight: bold; color: #dc2626; opacity: 0.22; letter-spacing: 10px; transform: rotate(-35deg); z-index: 1000; }
    </style>
</head>
<body>
    @if (! empty($sello_estado))
        <div class="sello-estado">{{ $sello_estado }}</div>
    @endif
    <img class="franja-sup" src="{{ $recursos['franja_superior'] }}" alt="">
    <img class="franja-inf" src="{{ $recursos['franja_inferior'] }}" alt="">
    <img class="marca-agua" src="{{ $recursos['marca_agua'] }}" alt="">
    @if (in_array($estado['clave'], ['anulado', 'reemplazado'], true))
        <div class="anulado">{{ $estado['texto'] }}</div>
    @endif

    {{-- Cabecera: logo, RUC y contacto --}}
    <table class="cabecera">
        <tr>
            <td><img class="logo" src="{{ $recursos['logo'] }}" alt="Extintores Bruce Fire"></td>
            <td style="width: 62mm; padding-left: 4mm;">
                <div class="ruc">RUC: <span class="mono">{{ $empresa['ruc'] }}</span></div>
                @if ($empresa['telefonos'] || $empresa['email'])
                    <table class="contacto">
                        @foreach ($empresa['telefonos'] as $telefono)
                            <tr><td class="icono"><img src="{{ $recursos['icono_whatsapp'] }}" alt=""></td><td class="telefono">{{ $telefono }}</td></tr>
                        @endforeach
                        @if ($empresa['email'])
                            <tr><td class="icono"><img src="{{ $recursos['icono_gmail'] }}" alt=""></td><td class="correo">{{ $empresa['email'] }}</td></tr>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>

    {{-- Título y tarjeta de verificación --}}
    <table class="titulo">
        <tr>
            <td style="padding-right: 3mm;">
                @foreach ($titulo as $linea)
                    <div class="{{ $linea['clase'] }}" style="font-size: {{ $linea['tamano'] }}pt;">{{ $linea['texto'] }}</div>
                @endforeach
            </td>
            <td class="verifica">
                <div class="tarjeta">
                    <table>
                        <tr>
                            <td style="width: 24.5mm;"><img class="qr" src="{{ $qr['imagen'] }}" alt="QR de verificación"></td>
                            <td style="padding-left: 1.4mm;">
                                <div class="etiqueta">N.° DE CERTIFICADO</div>
                                <div class="numero">{{ $certificado['numero'] }}</div>
                                <div class="estado estado-{{ $estado['clave'] }}">
                                    <div class="texto">{{ $estado['texto'] }}</div>
                                    @if ($estado['fecha'])
                                        <div class="fecha">{{ $estado['fecha'] }}</div>
                                    @endif
                                </div>
                                @if ($certificado['revision'] > 0)
                                    <div class="revision">Revisión {{ $certificado['revision'] }} · {{ $certificado['revision_fecha'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
    @if (! empty($tipo['norma']))
        <div class="norma">{{ $tipo['norma'] }}</div>
    @endif

    {{-- Datos del cliente y del servicio --}}
    <table class="seccion">
        <tr>
            <td style="width: 55%; padding-right: 2.4mm;">
                <table class="barra"><tr><td class="texto">Datos del cliente</td><td class="cola"><img src="{{ $recursos['cola'] }}" alt=""></td></tr></table>
                <table class="datos">
                    <tr><td class="dato-etiqueta">Razón social</td><td class="dato-valor">{{ $cliente['razon_social'] }}</td></tr>
                    <tr><td class="dato-etiqueta">{{ $cliente['documento_tipo'] }}</td><td class="dato-valor mono">{{ $cliente['documento'] }}</td></tr>
                    @if (! empty($cliente['nombre_comercial']))
                        <tr><td class="dato-etiqueta">Nombre comercial</td><td class="dato-valor">{{ $cliente['nombre_comercial'] }}</td></tr>
                    @endif
                    <tr><td class="dato-etiqueta">Dirección</td><td class="dato-valor">{{ $cliente['direccion'] }}</td></tr>
                    @if (! empty($cliente['referencia']))
                        <tr><td class="dato-etiqueta">{{ $cliente['referencia']['etiqueta'] }}</td><td class="dato-valor">{{ $cliente['referencia']['valor'] }}</td></tr>
                    @endif
                </table>
            </td>
            <td style="padding-left: 2.4mm;">
                <table class="barra"><tr><td class="texto">Datos del servicio</td><td class="cola"><img src="{{ $recursos['cola'] }}" alt=""></td></tr></table>
                <table class="datos">
                    <tr><td class="dato-etiqueta">Fecha de emisión</td><td class="dato-valor mono">{{ $certificado['emision'] }}</td></tr>
                    @if ($certificado['vencimiento'])
                        <tr><td class="dato-etiqueta">Vencimiento</td><td class="dato-valor mono">{{ $certificado['vencimiento'] }}</td></tr>
                    @endif
                    @if ($certificado['vigencia'])
                        <tr><td class="dato-etiqueta">Vigencia</td><td class="dato-valor">{{ $certificado['vigencia'] }}</td></tr>
                    @endif
                    @if ($certificado['tipo_atencion'])
                        <tr><td class="dato-etiqueta">Tipo de atención</td><td class="dato-valor">{{ $certificado['tipo_atencion'] }}</td></tr>
                    @endif
                    @if ($certificado['orden'])
                        <tr><td class="dato-etiqueta">Orden de servicio</td><td class="dato-valor mono">{{ $certificado['orden'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if (! empty($tipo['declaracion']))
        <p class="declaracion">{!! str_replace(e($empresa['razon_social']), '<b>'.e($empresa['razon_social']).'</b>', e($tipo['declaracion'])) !!}</p>
    @endif

    {{-- Tabla de equipos o componentes --}}
    @if ($columnas && $filas)
        <div class="seccion">
            <table class="barra"><tr><td class="texto">{{ $tituloTabla }}</td><td class="cola"><img src="{{ $recursos['cola'] }}" alt=""></td></tr></table>
            <table @class(['tabla', 'compacta' => count($filas) > 12])>
                <thead>
                    <tr>
                        @foreach ($columnas as $columna)
                            <th @class(['centro' => in_array($columna['clave'], $centradas, true)])>{{ $columna['titulo'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $indice => $fila)
                        <tr @class(['par' => $indice % 2 === 1])>
                            @foreach ($columnas as $columna)
                                <td @class(['centro' => in_array($columna['clave'], $centradas, true)])>
                                    @if (($columna['tipo'] ?? '') === 'resultado')
                                        @php($claveResultado = $fila['resultado'] ?? 'operativo')
                                        <span class="pastilla p-{{ $claveResultado }}">{{ $resultados[$claveResultado] ?? $claveResultado }}</span>
                                    @else
                                        {{ ($fila[$columna['clave']] ?? '') !== '' ? $fila[$columna['clave']] : '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Pruebas realizadas (checklist C / NC / N/A) --}}
    @if ($pruebas)
        <table class="seccion" style="page-break-inside: avoid;">
            <tr>
                @foreach (array_chunk($pruebas, $mitadPruebas, true) as $lado => $mitad)
                    <td style="width: 50%; {{ $lado === 0 ? 'padding-right: 2.4mm;' : 'padding-left: 2.4mm;' }}">
                        <table class="barra"><tr><td class="texto">{{ $lado === 0 ? 'Pruebas realizadas' : '' }}</td><td class="cola"><img src="{{ $recursos['cola'] }}" alt=""></td></tr></table>
                        <table class="tabla pruebas">
                            <thead><tr><th>N.°</th><th>Verificación</th><th class="centro">C</th><th class="centro">NC</th><th class="centro">N/A</th></tr></thead>
                            <tbody>
                                @foreach ($mitad as $numero => $prueba)
                                    <tr @class(['par' => $numero % 2 === 1])>
                                        <td class="n">{{ str_pad((string) ($numero + 1), 2, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $prueba['texto'] }}@if ($prueba['valor'])<span class="valor"> · {{ $prueba['valor'] }}</span>@endif</td>
                                        @foreach (['C' => 'caja-c', 'NC' => 'caja-nc', 'NA' => 'caja-na'] as $opcion => $claseMarcada)
                                            <td class="col-marca"><span @class(['caja', 'check', $claseMarcada => $prueba['estado'] === $opcion])>@if ($prueba['estado'] === $opcion)&#10004;@endif</span></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    {{-- Bloques técnicos del tipo (por ejemplo, extintores) --}}
    @if ($bloques || $conAsneex)
        <div class="seccion" style="page-break-inside: avoid;">
            <table class="bloques">
                <tr>
                    @foreach ($bloques as $bloque)
                        <td class="bloque">
                            <div class="bloque-titulo">{{ $bloque['titulo'] }}</div>
                            <div class="bloque-texto">{{ $bloque['texto'] }}</div>
                        </td>
                    @endforeach
                    @if ($conAsneex)
                        <td style="width: 21mm; text-align: center; vertical-align: middle;">
                            <img src="{{ $recursos['logo_asneex'] }}" alt="ASNEEX" style="width: 17mm;">
                            <div class="etiqueta" style="margin-top: 0.5mm;">ASOCIADO</div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- Resultado, responsabilidad y firmas van siempre juntos --}}
    <div style="page-break-inside: avoid;">
    <div class="seccion">
        <table class="barra"><tr><td class="texto">Resultado de la evaluación</td><td class="cola"><img src="{{ $recursos['cola'] }}" alt=""></td></tr></table>
        <table class="resultado">
            <tr>
                @foreach ($resultados as $clave => $texto)
                    <td @class(['celda', 'elegida' => $resultado === $clave])>
                        <table class="opcion">
                            <tr>
                                <td class="marca"><span @class(['caja', 'check', 'caja-c' => $resultado === $clave])>@if ($resultado === $clave)&#10004;@endif</span></td>
                                <td class="texto">{{ $texto }}</td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        </table>
        @if (! empty($observaciones))
            <div class="observaciones"><b>Observaciones:</b> {{ $observaciones }}</div>
        @endif
        @if ($estado['clave'] === 'anulado' && ! empty($certificado['anulado_motivo']))
            <div class="observaciones" style="border-left-color: #b91c1c; background: #fdecec;"><b>Certificado anulado:</b> {{ $certificado['anulado_motivo'] }}</div>
        @endif
    </div>

    @if (! empty($tipo['responsabilidad']))
        <p class="responsabilidad">{{ $tipo['responsabilidad'] }}</p>
    @endif

    {{-- Firmas con el sello al centro --}}
    <table class="firmas">
        <tr>
            @foreach ($firmantes as $indice => $firmante)
                @if ($indice === $posicionSello)
                    <td class="sello-celda"><img class="sello" src="{{ $recursos['sello'] }}" alt="Sello Bruce Fire"></td>
                @endif
                <td>
                    @if (! empty($firmante['firma']))
                        <img class="firma-img" src="{{ $firmante['firma'] }}" alt="">
                    @else
                        <div class="firma-vacia"></div>
                    @endif
                    <div class="firma-linea">
                        <div class="firma-nombre">{{ $firmante['nombre'] }}</div>
                        <div class="firma-cargo">{{ $firmante['cargo'] }}</div>
                        @if (! empty($firmante['cip']))
                            <div class="firma-cargo">CIP N.° {{ $firmante['cip'] }}</div>
                        @endif
                    </div>
                </td>
            @endforeach
            @if ($posicionSello >= count($firmantes))
                <td class="sello-celda"><img class="sello" src="{{ $recursos['sello'] }}" alt="Sello Bruce Fire"></td>
            @endif
        </tr>
    </table>
    </div>
</body>
</html>
