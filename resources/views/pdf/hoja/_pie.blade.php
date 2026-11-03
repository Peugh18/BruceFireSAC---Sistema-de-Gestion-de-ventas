{{--
    Pie de la hoja única. Recibe $company, $textoPie (lo propio de cada
    documento) y, solo en los electrónicos, $qrBase64 (el QR de SUNAT).
--}}
<table class="footer">
    <tr>
        @if(! empty($qrBase64))
            <td class="footer-qr">
                <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR">
            </td>
        @endif
        <td>
            {!! $textoPie !!}
            <div class="thanks">{{ $company->mensaje_agradecimiento ?: 'Gracias por su preferencia.' }}</div>
            @if($company->leyenda_pie)
                <div class="leyenda">{{ $company->leyenda_pie }}</div>
            @endif
        </td>
    </tr>
</table>
