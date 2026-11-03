{{--
    Firmas de la hoja única. $firmas: lista de
    ['nombre' => ..., 'detalle' => ..., 'imagen' => data URI o null].
--}}
<table class="firmas">
    <tr>
        @foreach($firmas as $firma)
            <td>
                @if(! empty($firma['imagen']))
                    <img class="firma-img" src="{{ $firma['imagen'] }}" alt="Firma">
                @endif
                <div class="linea-firma">{{ $firma['nombre'] }}</div>
                @if(! empty($firma['detalle']))
                    <div class="detalle-firma">{{ $firma['detalle'] }}</div>
                @endif
            </td>
        @endforeach
    </tr>
</table>
