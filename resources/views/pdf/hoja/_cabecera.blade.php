{{--
    Cabecera de la hoja única: logo y empresa a la izquierda, recuadro con el
    tipo y el número del documento a la derecha. Igual en todo lo que emitimos.
    Recibe: $company, $logo, $ubicacion, $tipoDocumento, $numeroDocumento y,
    opcional, $extraDocumento (una línea bajo el número).
--}}
@php $anchoLogo = $logo ? (int) round($logo['ancho'] * 0.72) : 0; @endphp
<table class="header">
    <tr>
        @if($logo)
            <td style="width: {{ $anchoLogo + 10 }}px; padding-right: 10px;">
                <img src="{{ $logo['src'] }}" alt="logo" style="width: {{ $anchoLogo }}px; height: {{ (int) round($logo['alto'] * 0.72) }}px;">
            </td>
        @endif
        <td>
            <div class="company-name">{{ $company->razon_social }}</div>
            @if(! $logo && $company->nombre_comercial && $company->nombre_comercial !== $company->razon_social)
                <div class="company-line">{{ $company->nombre_comercial }}</div>
            @endif
            @if($company->direccion)
                <div class="company-line">{{ $company->direccion }}@if($ubicacion), {{ $ubicacion }}@endif</div>
            @endif
            @if($company->telefono || $company->email || $company->sitio_web)
                <div class="company-line">{{ collect([$company->telefono ? 'Telf. '.$company->telefono : null, $company->email, $company->sitio_web])->filter()->implode('  ·  ') }}</div>
            @endif
        </td>
        <td style="width: 168px;">
            <div class="doc-box">
                <div class="ruc">R.U.C. {{ $company->ruc }}</div>
                <div class="tipo">{{ $tipoDocumento }}</div>
                <div class="numero">{{ $numeroDocumento }}</div>
                @if(! empty($extraDocumento))
                    <div class="extra">{{ $extraDocumento }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<div class="rule"></div>
