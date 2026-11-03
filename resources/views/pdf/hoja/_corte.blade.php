{{--
    Línea de corte a media hoja A4. Solo aparece cuando el documento entra en
    la mitad de arriba ($mediaHoja, lo decide App\Support\HojaPdf).
--}}
@if($mediaHoja ?? false)
    <div class="corte"><span><b>✂</b> cortar aquí: la mitad de abajo sirve para otro documento</span></div>
@endif
