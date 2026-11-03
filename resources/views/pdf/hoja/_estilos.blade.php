{{--
    Estilos de la hoja única: todo lo que emitimos (factura, boleta, notas,
    nota de venta, cotización, guía, constancia y acta) se dibuja con ellos.
    El color sale de «Diseño de los documentos» del Gerente ($marca).
--}}
@page { margin: 9mm 10mm 8mm; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #222; line-height: 1.35; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }

/* Cabecera: logo · empresa · recuadro SUNAT con el tipo en la banda de color */
.header td { vertical-align: middle; }
.company-name { font-size: 12.5px; font-weight: bold; color: #111; }
.company-line { font-size: 8px; color: #555; margin-top: 1px; }
.doc-box { border: 1.3px solid {{ $marca['color'] }}; border-radius: 3px; text-align: center; }
.doc-box .ruc { font-size: 9.6px; font-weight: bold; color: #111; padding: 4px 4px 2px; letter-spacing: 0.5px; }
.doc-box .tipo { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: {{ $marca['texto'] }}; background: {{ $marca['color'] }}; padding: 3px 4px; }
.doc-box .numero { font-size: 12px; font-weight: bold; color: #111; padding: 3px 4px 4px; letter-spacing: 0.7px; }
.doc-box .extra { font-size: 7.6px; color: #444; padding: 0 4px 4px; }
.rule { border-top: 1.6px solid {{ $marca['color'] }}; margin: 7px 0 0; }

/* Datos: "Etiqueta : valor" en dos columnas */
.datos { margin-top: 6px; }
.datos td { padding: 1.5px 0; font-size: 9px; }
.k { width: 74px; color: #555; font-weight: bold; }
.k2 { width: 92px; color: #555; font-weight: bold; }
.sep { width: 10px; color: #555; }
.v { color: #111; }
.v-strong { color: #111; font-weight: bold; font-size: 9.6px; }
.vacio { border-bottom: 0.6px dotted #b5b5b5; }

/* Bloque destacado con la barra del color de la marca */
.destino { margin-top: 5px; border: 0.8px solid #d7d7d7; border-left: 3px solid {{ $marca['color'] }}; background: {{ $marca['suave'] }}; }
.destino td { padding: 4px 8px; vertical-align: middle; }
.destino .titulo { font-size: 7.4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; color: #555; }
.destino .valor { font-size: 12px; font-weight: bold; color: #111; letter-spacing: 0.6px; }
.destino .valor-texto { font-size: 9.6px; font-weight: bold; color: #111; }
.destino .detalle { font-size: 8.4px; color: #444; }

/* Detalle */
.items { margin-top: 6px; }
.items th { font-size: 7.4px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 0.4px; padding: 3px 5px; text-align: left; background: #f2f2f2; border-top: 1px solid #333; border-bottom: 1px solid #333; }
.items td { padding: 4px 5px; font-size: 9px; border-bottom: 0.6px solid #e1e1e1; }
.items tr.last td { border-bottom: 1px solid #333; }
.num, .items th.num { text-align: right; white-space: nowrap; }
.center, .items th.center { text-align: center; }
.code { font-size: 8px; color: #666; }

/* Totales */
.summary { margin-top: 6px; }
.letras { font-size: 8.2px; border: 0.6px solid #cfcfcf; padding: 3px 6px; }
.letras b { color: #111; }
.obs { margin-top: 3px; font-size: 8.4px; color: #333; }
.totals td { padding: 1.5px 6px; font-size: 9px; }
.totals .t-label { color: #444; }
.totals .t-value { text-align: right; white-space: nowrap; color: #111; }
.totals .grand td { padding: 4px 6px; font-size: 11.5px; font-weight: bold; color: {{ $marca['texto'] }}; background: {{ $marca['color'] }}; }

/* Bloques opcionales */
.box { margin-top: 5px; border: 0.6px solid #cfcfcf; padding: 4px 7px; font-size: 8.2px; color: #333; }
.box-title { font-size: 7.3px; font-weight: bold; color: #111; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.mini th { font-size: 7.2px; text-transform: uppercase; color: #555; text-align: left; padding: 2px 4px; border-bottom: 0.6px solid #cfcfcf; }
.mini td { font-size: 8.2px; padding: 2px 4px; }

/* Firmas */
.firmas { margin-top: 30px; }
.firmas td { width: 50%; padding: 0 18px; text-align: center; vertical-align: bottom; }
.firmas .firma-img { height: 36px; margin-bottom: 2px; }
.firmas .linea-firma { border-top: 0.8px solid #333; padding-top: 4px; font-size: 8.2px; color: #111; font-weight: bold; }
.firmas .detalle-firma { font-size: 7.6px; color: #555; }

/* Pie */
.footer { margin-top: 8px; border-top: 0.6px solid #cfcfcf; padding-top: 5px; }
.footer td { vertical-align: middle; font-size: 7.6px; color: #555; }
.footer-qr { width: 72px; }
.footer-qr img { width: 66px; height: 66px; }
.thanks { font-size: 8.4px; font-style: italic; color: #333; margin-top: 2px; }
.leyenda { font-size: 7.8px; font-style: italic; color: #777; }

/* Línea de corte a media hoja A4 (148,5 mm; el contenido empieza a 9 mm) */
.corte { position: fixed; top: 139.5mm; left: -10mm; right: -10mm; border-top: 0.7px dashed #9a9a9a; }
.corte span { position: absolute; top: -6px; left: 14mm; font-size: 7px; color: #9a9a9a; background: #fff; padding: 0 3px; }
.corte b { font-family: DejaVu Sans, sans-serif; font-weight: normal; }

/* Marca de agua de un documento anulado */
.marca-anulado { position: fixed; top: 330px; left: 0; right: 0; text-align: center; font-size: 110px; font-weight: bold; color: #dc2626; opacity: 0.22; letter-spacing: 10px; transform: rotate(-35deg); }
