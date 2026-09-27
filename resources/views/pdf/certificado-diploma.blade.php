{{--
    Diploma de capacitación estilo C (A4 horizontal): franjas en las esquinas,
    logo grafito, título itálico grueso, subtítulo rojo entre líneas finas, nombre y
    curso ajustados al ancho, QR con número y estado, firma del instructor con el
    sello encima y marca de agua. Mismos datos que el resto de certificados.
--}}
@php
    $estado = $certificado['estado'];
    $instructor = $firmantes[0] ?? ['nombre' => '', 'cargo' => 'Instructor', 'cip' => null, 'firma' => null];

    // Si en la venta se escribió otro instructor, su nombre manda (sin la firma de otro).
    if (! empty($capacitacion['instructor']) && $capacitacion['instructor'] !== $instructor['nombre']) {
        $instructor = ['nombre' => $capacitacion['instructor'], 'cargo' => 'Instructor', 'cip' => null, 'firma' => null];
    }
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

        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Texto', sans-serif; color: #1b1918; }
        .pagina { position: relative; width: 297mm; height: 210mm; overflow: hidden; page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
        .capa { position: absolute; }
        .centro { left: 30mm; right: 30mm; text-align: center; }
        table { border-collapse: collapse; }
        td { padding: 0; vertical-align: middle; }

        .franja-sup { top: 0; left: 0; width: 61mm; }
        .franja-inf { bottom: 0; right: 0; width: 61mm; }
        .marca-agua { top: 42mm; left: 95mm; width: 107mm; }
        .anulado { top: 88mm; left: 20mm; width: 257mm; text-align: center; font-family: 'Titulo'; font-size: 92pt; color: #f2c4c4; transform: rotate(-18deg); }

        .logo { top: 11mm; }
        .logo img { width: 150mm; }
        .titulo { top: 47mm; font-family: 'Titulo'; font-size: 55pt; line-height: 1; letter-spacing: 0.4pt; white-space: nowrap; }
        .cinta { top: 71mm; }
        .cinta table { margin: 0 auto; border-collapse: collapse; }
        .cinta td { vertical-align: middle; padding: 0; }
        .cinta .texto { color: #d20404; font-family: 'Cond'; font-weight: bold; font-size: 16pt; letter-spacing: 6pt; line-height: 1; text-align: center; white-space: nowrap; padding: 0 2.9mm 0 5mm; }
        .cinta .linea { width: 42mm; }
        .cinta .linea div { border-top: 0.9pt solid #2e2d2c; height: 0; }
        .cinta .punto { width: 1.8mm; }
        .cinta .punto div { width: 1.8mm; height: 1.8mm; background: #d20404; }

        .otorgado { top: 88mm; font-family: 'Cond'; font-weight: normal; font-size: 12pt; letter-spacing: 1.4pt; color: #2e2d2c; }
        .nombre { top: 101mm; font-family: 'Subtitulo'; line-height: 1.05; white-space: nowrap; }
        .linea-nombre { top: 115.5mm; left: 51mm; right: 51mm; border-top: 1.1pt solid #d20404; }
        .declaracion { top: 121.5mm; left: 25mm; right: 25mm; font-size: 11.8pt; line-height: 1.35; color: #3f3d3b; }
        .declaracion b { font-family: 'Texto'; font-weight: bold; color: #1b1918; }
        .curso { top: 132mm; font-family: 'Subtitulo'; color: #d20404; line-height: 1.05; white-space: nowrap; }
        .duracion { top: 146mm; font-size: 11.8pt; color: #3f3d3b; }
        .duracion b { font-family: 'Texto'; font-weight: bold; color: #d20404; }
        .personal { top: 117.5mm; font-family: 'Cond'; font-size: 10.5pt; color: #5c5a58; letter-spacing: 0.3pt; }
        .personal b { font-family: 'Cond'; font-weight: bold; color: #1b1918; }
        .por-trabajador .declaracion { top: 125mm; }
        .por-trabajador .curso { top: 135mm; }
        .por-trabajador .duracion { top: 148.5mm; }
        .por-trabajador .sello { top: 160mm; left: 131mm; width: 30mm; }

        /* Con fotos: todo sube y se compacta para que entren 3 fotos grandes en la misma hoja */
        .fotos { top: 121mm; left: 30mm; right: 30mm; text-align: center; line-height: 0; }
        .fotos img { width: 62mm; height: 41.3mm; margin: 0 1.6mm; border: 0.8pt solid #d9d9d9; border-top: 1.6pt solid #d20404; }
        .con-fotos .logo { top: 8mm; }
        .con-fotos .logo img { width: 118mm; }
        .con-fotos .titulo { top: 33mm; font-size: 44pt; }
        .con-fotos .cinta { top: 51mm; }
        .con-fotos .cinta .texto { font-size: 13pt; letter-spacing: 5pt; padding: 0 3.2mm 0 5mm; }
        .con-fotos .cinta .linea { width: 34mm; }
        .con-fotos .otorgado { top: 63mm; font-size: 10.5pt; }
        .con-fotos .nombre { top: 68mm; }
        .con-fotos .linea-nombre { top: 81mm; }
        .con-fotos .declaracion { top: 84mm; font-size: 11pt; }
        .con-fotos .curso { top: 91mm; }
        .con-fotos .duracion { top: 103mm; font-size: 11pt; }
        .con-fotos .verifica { top: 162mm; }
        .con-fotos .sello { top: 160mm; left: 131mm; width: 30mm; }
        .con-fotos .firma { top: 166mm; }

        .verifica { top: 162mm; left: 10mm; width: 86mm; }
        .tarjeta { border: 0.8pt solid #cfcfcf; border-radius: 6pt; padding: 2mm; background: #ffffff; }
        .qr { width: 24mm; height: 24mm; }
        .etiqueta { font-family: 'Cond'; font-weight: bold; font-size: 6.8pt; color: #6b6965; letter-spacing: 0.8pt; }
        .numero { font-family: 'Cond'; font-weight: bold; font-size: 10.5pt; color: #d20404; white-space: nowrap; }
        .estado { margin-top: 1.4mm; border-radius: 4pt; padding: 1mm 1.6mm; text-align: center; color: #ffffff; font-family: 'Cond'; font-weight: bold; font-size: 7.8pt; letter-spacing: 0.6pt; }
        .estado-vigente { background: #0e7a3e; }
        .estado-vencido { background: #b45309; }
        .estado-anulado, .estado-reemplazado { background: #b91c1c; }
        .revision { margin-top: 0.8mm; font-size: 6.6pt; color: #6b6965; text-align: center; }

        .firma { top: 164mm; left: 194mm; width: 65mm; text-align: center; }
        .firma-img { height: 14mm; }
        .firma-vacia { height: 14mm; }
        .firma-linea { border-top: 0.8pt solid #1b1918; padding-top: 1.2mm; }
        .firma-nombre { font-family: 'Cond'; font-weight: bold; font-size: 11pt; }
        .firma-cargo { font-family: 'Cond'; font-size: 8.4pt; color: #d20404; letter-spacing: 1pt; text-transform: uppercase; }
        .sello { top: 155mm; left: 128mm; width: 36mm; }
    </style>
</head>
<body>
@foreach($paginas ?? [[
    'empresa' => $empresa, 'tipo' => $tipo, 'certificado' => $certificado, 'cliente' => $cliente,
    'firmantes' => $firmantes, 'capacitacion' => $capacitacion, 'qr' => $qr, 'diploma' => $diploma,
]] as $pagina)
    @php extract($pagina); @endphp
<div class="pagina {{ $capacitacion['modo'] === 'con_fotos' && ! empty($capacitacion['fotos']) ? 'con-fotos' : '' }} {{ ! empty($capacitacion['personal_de']) ? 'por-trabajador' : '' }}">
    <img class="capa marca-agua" src="{{ $recursos['marca_agua'] }}" alt="">
    <img class="capa franja-sup" src="{{ $recursos['franja_superior'] }}" alt="">
    <img class="capa franja-inf" src="{{ $recursos['franja_inferior'] }}" alt="">
    @if (in_array($estado['clave'], ['anulado', 'reemplazado'], true))
        <div class="capa anulado">{{ $estado['texto'] }}</div>
    @endif

    <div class="capa centro logo"><img src="{{ $recursos['logo'] }}" alt="Extintores Bruce Fire"></div>
    <div class="capa centro titulo">{{ mb_strtoupper($tipo['titulo'] ?: 'CERTIFICADO') }}</div>
    <div class="capa centro cinta">
        <table>
            <tr>
                <td class="linea"><div></div></td>
                <td class="punto"><div></div></td>
                <td class="texto">{{ mb_strtoupper($tipo['subtitulo'] ?: 'DE CAPACITACIÓN') }}</td>
                <td class="punto"><div></div></td>
                <td class="linea"><div></div></td>
            </tr>
        </table>
    </div>

    <div class="capa centro otorgado">OTORGADO A</div>
    <div class="capa centro nombre" style="font-size: {{ $diploma['tamano_nombre'] }}pt;">{{ mb_strtoupper((string) $cliente['razon_social']) }}</div>
    <div class="capa linea-nombre"></div>
    <div class="capa declaracion" style="text-align: center;">
        {!! str_replace(e($empresa['razon_social']), '<b>'.e($empresa['razon_social']).'</b>', e(trim((string) $tipo['declaracion']))) !!} en:
    </div>
    <div class="capa centro curso" style="font-size: {{ $diploma['tamano_curso'] }}pt;">{{ $capacitacion['curso'] }}</div>
    <div class="capa centro duracion">
        Con una duración de <b>{{ str_pad((string) $capacitacion['horas'], 2, '0', STR_PAD_LEFT) }} horas</b>, realizada en {{ $empresa['ciudad'] ?? 'Trujillo' }} el {{ $capacitacion['fecha_larga'] }}.
    </div>

    @if (! empty($capacitacion['personal_de']))
        <div class="capa centro personal">Personal de <b>{{ $capacitacion['personal_de'] }}</b>@if($capacitacion['dni']) · DNI {{ $capacitacion['dni'] }}@endif</div>
    @endif
    @if (! empty($capacitacion['fotos']))
        <div class="capa fotos">
            @foreach($capacitacion['fotos'] as $foto)
                <img src="{{ $foto }}" alt="Registro fotográfico de la capacitación">
            @endforeach
        </div>
    @endif

    <div class="capa verifica">
        <div class="tarjeta">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 25mm;"><img class="qr" src="{{ $qr['imagen'] }}" alt="QR de verificación"></td>
                    <td style="padding-left: 1.6mm;">
                        <div class="etiqueta">N.° DE CERTIFICADO</div>
                        <div class="numero">{{ $certificado['numero'] }}</div>
                        <div class="estado estado-{{ $estado['clave'] }}">
                            @if($estado['clave'] === 'vigente')<span style="font-family: 'DejaVu Sans', sans-serif;">&#10004;</span> @endif{{ $estado['texto'] }}@if ($estado['fecha']) {{ $estado['fecha'] }}@endif
                        </div>
                        @if ($certificado['revision'] > 0)
                            <div class="revision">Revisión {{ $certificado['revision'] }} · {{ $certificado['revision_fecha'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        <div class="etiqueta" style="margin-top: 1.2mm; text-align: center;">ESCANEE PARA VERIFICAR SU AUTENTICIDAD</div>
    </div>

    <div class="capa firma">
        @if (! empty($instructor['firma']))
            <img class="firma-img" src="{{ $instructor['firma'] }}" alt="">
        @else
            <div class="firma-vacia"></div>
        @endif
        <div class="firma-linea">
            <div class="firma-nombre">{{ $instructor['nombre'] }}</div>
            <div class="firma-cargo">{{ $instructor['cargo'] }}</div>
        </div>
    </div>
    <img class="capa sello" src="{{ $recursos['sello'] }}" alt="Sello Bruce Fire">
</div>
@endforeach
</body>
</html>
