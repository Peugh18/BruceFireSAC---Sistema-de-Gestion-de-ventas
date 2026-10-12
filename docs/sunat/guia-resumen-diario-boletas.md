Guía de Resumen Diario de
Boletas de Venta Electrónicas y
Notas Electrónicas
PROYECTO DE COMPROBANTES DE PAGO
ELECTRONICOS
Versión 2
Enero 2018

Guía de elaboración de documentos electrónicos XML - UBL 2.0
INDICE
1 RESUMEN DIARIO DE BOLETAS DE VENTA Y NOTAS VINCULADAS................................................. 4
1.1 REQUISITOS PARA EL RESUMEN DIARIO................................................................................................ 4
1.2 ESTRUCTURA DEL RESUMEN DIARIO SEGÚN NORMA UBL........................................................................ 7
1.3 ESTRUCTURA DEL RESUMEN DIARIO VS FORMATO XML......................................................................... 11
1.4 ESTRUCTURA DEL RESUMEN DIARIO SEGÚN EL ESTÁNDAR UBL 2.0......................................................... 16
A. Normas de Uso.......................................................................................................................... 16
A.1 Elementos del Resumen Diario de Boletas de Venta electrónicas y Notas electrónicas............... 16
B. EJEMPLO Resumen de Boletas de Venta................................................................................... 25
- 2 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
Registros de Cambios de la Guía
Fecha Versión Descripción Autor
Resumen diario de boletas de venta Proyecto de Libros y
31/05/2012 1
electrónicas y notas electrónicas Comprobantes Electrónicos
Resumen diario de boletas de venta
Proyecto de Comprobantes
08/01/2017 2 electrónicas y notas electrónicas
de Pago Electrónicos
(Vigente a partir 01.01.2018)
- 3 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
1. RESUMEN DIARIO DE BOLETAS DE VENTA Y NOTAS VINCULADAS
El emisor electrónico debe enviar el resumen diario a que se refiere el Anexo N° 05-A
“Resumen diario de boletas de venta electrónica y notas electrónicas (vigente desde el
01.01.2018) y el Anexo 09 - A Estándar UBL 2.0 de la Resolución de Superintendencia N°
117-2017, el día en que se emitieron las boletas de venta electrónicas y las notas
electrónicas vinculadas a aquellas o a mas tardar hasta el sétimo día calendario contado
desde el día siguiente.
1.1 Requisitos para el resumen diario
El Resumen diario de boletas de ventas y notas vinculadas debe estar expresado en
Nuevos Soles.
En este sentido, es necesario establecer la nomenclatura de representación del valor de
los datos.
a caracter alfabético
n caracter numérico
an caracter alfanumérico
a3 3 caracteres alfabéticos de longitud fija
n3 3 caracteres numéricos de longitud fija
an3 3 caracteres alfa-numéricos de longitud fija
a..3 hasta 3 caracteres alfabéticos
n..3 hasta 3 caracteres numéricos
an..3 hasta 3 caracteres alfa-numéricos
La obligatoriedad o no de un determinado elemento se identifica por las siguientes
nomenclaturas:
M Mandatorio u obligatorio
C Condicional u opcional
En la identificación del formato de datos se precisa lo siguiente:
n(12,2) Elemento numérico hasta12 enteros+punto decimal+ hasta dos
decimales.
n(2,2) Elemento numérico hasta 2 enteros+punto decimal+ hasta dos
decimales.
B AAA Elemento inicia con la letra B seguida de tres caracteres
YYYY-MM-DD formato fecha yyyy=año, mm=mes, dd=día.
En el siguiente cuadro se muestran las características del contenido de los campos,
solicitados por SUNAT para el resumen diario de boletas de venta y notas electrónicas.
- 4 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
N° CAMPOS DEFINIDOS CONDICION VALIDACIÓN (1)
DATOS DEL EMISOR ELECTRÓNICO Y EL DOCUMENTO
Número de RUC del emisor Estos datos serán parte del
nombre del archivo. Todo los
Fecha de emisión de los
documentos reportados,
documentos
corresponderán a la misma fecha
de emisión
DETALLE DE CADA LINEA DEL RESUMEN
1 Tipo de documento M Según catálogo N.° 1 del Anexo
N.° 8.( 03- Boleta,07- nota de
crédito o 08 Nota de débito)
2 Número de serie del documento M La serie debe ser alfanumérica de
4 posiciones. Primera posición
debe ser "B"
3 Número correlativo del M El número correlativo debe tener
documento hasta 8 caracteres.
4 Tipo de documento de Identidad M Alfanumérico
del adquirente o usuario
5 Número de documento de M Alfanumérico
Identidad del adquirente o
usuario
6 Total valor de venta - M Campo lleno.
operaciones gravadas
7 Total valor de venta - M Campo lleno.
operaciones exoneradas
8 Total valor de venta - M Campo lleno.
operaciones inafectas
9 Importe total de sumatoria otros C Campo lleno.
cargos del ítem
10 Total ISC C Campo lleno.
11 Total IGV M Campo lleno.
12 Total Otros tributos C Campo lleno.
13 Importe total de la venta, cesión M Campo lleno.
en uso o del servicio prestado
14 Total valor de venta - C Campo lleno.
operaciones gratuitas
15 Tipo de documento que modifica C Solo se admite códigos 03 (boleta
de venta) y 12 (ticket)
16 Número de serie de la boleta de C La serie puede ser alfanumérica
venta que modifica de 4 posiciones (primera posición
debe ser "B") o numérica de 4
posiciones. Si se trata de tickets
consignar "-"
17 Número correlativo de la boleta C El número correlativo debe tener
de venta que modifica hasta 8 caracteres.
18 Régimen de percepción C Numérico de dos posiciones
19 Base imponible percepción C Numérico Hasta 12 enteros y
hasta 2 decimales
- 5 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
20 Monto de la percepción C Numérico. Hasta 12 enteros y
hasta 2 decimales
21 Monto total a cobrar incluida la C Numérico Hasta 12 enteros y
percepción hasta 2 decimales
22 Estado M Numérico de una posición. Se
indicará respecto de cada línea,
según corresponda, los códigos:
1 = Adicionar
2 = Modificar
3 = Anulado
- 6 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0

1.2 Estructura del resumen diario según norma UBL

ESTRUCTURA XML SUMMARYDOCUMENTS - PERU
|       |         |         |     |               |     |              |               |
| ----- | ------- | ------- | --- | ------------- | --- | ------------ | ------------- |
|       |         |         |     |               |     |              |               |
|       |         |         |     | Etiqueta XML  |     | Descripción  | Cardinalidad  |
                        Documento utilizado para informar el reporte de boletas y
                              SummaryDocuments  notas relacionadas a boletas
|      |     |     |     |     |     |     |     |
| ---- | --- | --- | --- | --- | --- | --- | --- |
                         Contenedor de Componentes de extensión. Podrán
incorporarse nuevas definiciones estructuradas cuando sean de
./ext:UBLExtensions
interés conjunto para emisores y receptores, y no estén ya
                           definidas en el esquema del resumen de boletas de venta.  1
                              ./ext:UBLExtension/ext:ExtensionContent  Componente de extensión para especificar la firma XMLDSIG   1
|     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- |
                     ./ds:Signature @Id  Identificación de la firma dentro del documento  1
|     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- |
información sobre el valor de la firma e información sobre los
|     |       |          | ./ds:SignedInfo  |     | datos a firmar  |     | 1   |
| --- | ----- | -------- | ---------------- | --- | --------------- | --- | --- |
            Indica cómo se debe transformar a forma canónica el elemento
./ds:CanonicalizationMethod@Algorithm
|     |       |       |     |     | <Signinfo> antes de realizar la firma  |     | 1   |
| --- | ----- | ----- | --- | --- | -------------------------------------- | --- | --- |
|     |       |       |     |     |                                        |     |     |
Especifica qué tipo de algoritmo de firma que se utilizará para
               ./ds:SignatureMethod@Algorithm  obtener la firma  1
              Identifica al objeto de datos que se va a firmar, Si su valor es
|     |       |       | ./ds:Reference@URI  |     | cadena vacía identifica al documento completo que contiene la  |     |     |
| --- | ----- | ----- | ------------------- | --- | -------------------------------------------------------------- | --- | --- |
|     |       |       |                     |     | firma                                                          |     | 1   |
              Indica un paso realizado en el procesamiento de cálculo del
               ./ds:Reference/ds:Transforms/ds:Transform@Algorithm  hash.  1
|     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- |
               ./ds:Reference/ds:DigestMethod@Algorithm  Define la función hash utilizada  1
|     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- |
                  ./ds:Reference/ds:DigestValue  Es el valor hash codificado en Base64  1
|     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- |
            ./ds:SignatureValue  Contiene la firma codificada en Base64  1
                Es una estructura  que contiene información del certificado
                           ./ds:KeyInfo/ds:X509Data/ds:X509Certificate  firmante  1
|        |         |         |                     |     |                   |     |     |
| ------ | ------- | ------- | ------------------- | --- | ----------------- | --- | --- |
|        |         |         |                     |     |                   |     |     |
|        |         |         | ./cbc:UBLVersionID  |     | Versión del UBL   |     | 1   |
|        |         |         |                     |     |                   |     |     |
- 7 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
   ./cbc:CustomizationID  Versión de la estructura del documento  1
|     |         |         |           |                            |     |
| --- | ------- | ------- | --------- | -------------------------- | --- |
|     |         |         | ./cbc:ID  | Identificador del resumen  | 1   |
|     |         |         |           |                            |     |
   ./cbc:IssueDate  Fecha de generación del resumen (yyyy-mm-dd)  1
|     |         |         |     |     |     |
| --- | ------- | ------- | --- | --- | --- |
   ./cbc:ReferenceDate  Fecha de emisión de los documentos (yyyy-mm-dd)  1
|        |         |         |      |     |     |
| ------ | ------- | ------- | ---- | --- | --- |
                                                 ./cac:Signature  Referencia a la Firma Digital   1
|        |     |     |   ./cbc:ID  | Identificador de la firma  | 1   |
| ------ | --- | --- | ----------- | -------------------------- | --- |
                                            ./cac:SignatoryParty     1
                             ./cac:PartyIdentification  Parte firmante  1
                      ./cbc:ID  Identificación de la parte firmante  1
|        |             |                |                  |                              |       |
| ------ | ----------- | -------------- | ---------------- | ---------------------------- | ----- |
|        |             |                |                  |                              |       |
|        |             |                |                  |                              |       |
|        |             |                | ./cac:PartyName  |                              | 0..1  |
|        |             |                |                  |                              |       |
|        |             |                | ./cbc:Name       | Nombre de la parte firmante  | 1     |
|        |             |                |                  |                              |       |
|        |             |                |                  |                              |       |
                    Asociación con  la firma codificada (en formato XMLDSIG, por
                        ./cac:DigitalSignatureAttachment  ejemplo)  1
        Información acerca de un documento vinculado. Los vínculos
pueden ser externos (referenciados mediante un elemento
URI),  internos (accesibles mediante un elemento MIME) o
./cac:ExternalReference
pueden estar contenidos dentro del mismo documento en el
que se alude a ellos (mediante elementos Documento
|     |       |     |     | Incrustado)  | 1   |
| --- | ----- | --- | --- | ------------ | --- |
                Identificador de Recurso Uniforme (o URI) que identifica la
|        |                 |                   | ./cbc:URI  | localización de la firma  | 1   |
| ------ | --------------- | ----------------- | ---------- | ------------------------- | --- |
|        |                 |                   |            |                           |     |
|        |                 |                   |            |                           |     |
|        |                 |                   |            |                           |     |
|        |                 |                   |            |                           |     |
                           ./cac:AccountingSupplierParty  Datos del Emisor del documento  1
|     |       |         |                                  |                |     |
| --- | ----- | ------- | -------------------------------- | -------------- | --- |
|     |       |         | ./cbc:CustomerAssignedAccountID  | Número de RUC  | 1   |
|     |       |         |                                  |                |     |
      ./cbc:AdditionalAccountID  Tipo de documento de identificación   1
|        |          |             |                           |     |     |
| ------ | -------- | ----------- | ------------------------- | --- | --- |
|        |          |             | ./cac:Party               |     | 1   |
|        |          |             |   ./cac:PartyLegalEntity  |     | 1   |
|        |          |             |                           |     |     |
- 8 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
            ./cbc:RegistrationName  Apellidos y nombres o denominación o razón social  1
|        |             |             |      |     |     |
| ------ | ----------- | ----------- | ---- | --- | --- |
|        |             |             |      |     |     |
|        |             |             |      |     |     |
                                                 ./sac:SummaryDocumentsLine  Items de consolidado de documentos  1..n
|        |     |     |   ./cbc:LineID  | Número de fila  | 1   |
| ------ | --- | --- | --------------- | --------------- | --- |
                          ./cbc:DocumentTypeCode  Código del Tipo  documento a referenciar en el presente ítem  1
                          ./cbc:ID  Serie y número del comprobante de pago  1
|        |          |         |      |     |     |
| ------ | -------- | ------- | ---- | --- | --- |
|        |          |         |      |     |     |
                        ./cac:AccountingCustomerParty  Datos del adquiriente o usuario del comprobante de pago  1
|     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- |
         ./cbc:CustomerAssignedAccountID  Número de documento de identidad  1
|     |     |         |     |     |     |
| --- | --- | ------- | --- | --- | --- |
         ./cbc:AdditionalAccountID  Tipo de documento de identidad  1
|     |          |             |     |     |     |
| --- | -------- | ----------- | --- | --- | --- |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
                        ./cac:BillingReference  Referencia del comprobante modificado   0..1
|     |          |             |                                 |                                 |     |
| --- | -------- | ----------- | ------------------------------- | ------------------------------- | --- |
|     |          |             | ./cac:InvoiceDocumentReference  |                                 | 1   |
|     |          |             |                                 |                                 |     |
|     |          |             | ./cbc:ID                        | Serie y número del comprobante  | 1   |
|     |          |             |                                 |                                 |     |
|     |          |             | ./cbc:DocumentTypeCode          | Tipo de comprobante             | 1   |
|     |          |             |                                 |                                 |     |
|     |          |             |                                 |                                 |     |
|     |          |             |                                 |                                 |     |
|     |          |             |                                 |                                 |     |
|     |          |             |                                 |                                 |     |
                        ./sac:SUNATPerceptionSummaryDocumentReference  Información de Percepciones  0..1
|     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- |
         ./sac:SUNATPerceptionSystemCode  Régimen de percepción  1
|     |     |         |     |     |     |
| --- | --- | ------- | --- | --- | --- |
         ./sac:SUNATPerceptionPercent  Tasa de la percepción  1
|     |     |         |     |     |     |
| --- | --- | ------- | --- | --- | --- |
         ./cbc:TotalInvoiceAmount  Monto de la percepción  1
|     |     |         |     |     |     |
| --- | --- | ------- | --- | --- | --- |
         ./sac:SUNATTotalCashed  Monto Total incluida la percepción  1
|     |     |         |     |     |     |
| --- | --- | ------- | --- | --- | --- |
         ./cbc:TaxableAmount  Base imponible de la percepción  1
|     |          |             |                      |                            |     |
| --- | -------- | ----------- | -------------------- | -------------------------- | --- |
|     |          |             |                      |                            |     |
|     |          |             |                      |                            |     |
|     |          |             |                      |                            |     |
|     |          |             |                      |                            |     |
|     |          |             | ./cac:Status         | Estado del ítem            | 1   |
|     |          |             |                      |                            |     |
|     |          |             | ./cbc:ConditionCode  | Código de estado del ítem  | 1   |
|     |          |             |                      |                            |     |
- 9 -

Guía de elaboración de documentos electrónicos XML - UBL 2.0
|     |          |             |     |     |     |
| --- | -------- | ----------- | --- | --- | --- |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
      ./sac:TotalAmount  Importe total de la venta, cesión en uso o del servicio prestado  1
|     |       |         |     |     |     |
| --- | ----- | ------- | --- | --- | --- |
|     |       |         |     |     |     |
                    Referencia de Valores de venta asociados al comprobante de
|     |          |             | ./sac:BillingPayment         | pago           | 1..n  |
| --- | -------- | ----------- | ---------------------------- | -------------- | ----- |
|     |          |             |                              |                |       |
|     |          |             | ./cbc:PaidAmount@currencyID  | Importe Total  | 1     |
|     |          |             |                              |                |       |
Identificación del tipo de importe total
01: Valor de venta de las operaciones gravadas con el IGV
|     |     |     | ./cbc:InstructionID  |     | 1   |
| --- | --- | --- | -------------------- | --- | --- |
02: Valores de venta de las operaciones exoneradas del IGV
03: Valores de venta de las operaciones inafectas del IGV
04: Valor de venta de las exportaciones del item
|     |          |             |     | 05: Valor de venta de las operaciones gratuitas  |     |
| --- | -------- | ----------- | --- | ------------------------------------------------ | --- |
|     |          |             |     |                                                  |     |
|     |          |             |     |                                                  |     |
|     |          |             |     |                                                  |     |
|     |          |             |     |                                                  |     |
|     |          |             |     |                                                  |     |
                        ./cac:AllowanceCharge  Información acerca del Importe Total Otros Cargos  0..n
|       |         |         |                        |                     |     |
| ----- | ------- | ------- | ---------------------- | ------------------- | --- |
|       |         |         | ./cbc:ChargeIndicator  | Indicador de cargo  | 1   |
|       |         |         |                        |                     |     |
         ./cbc:Amount@currencyID  Importe total de otros cargos del item  1
|     |          |             |     |     |     |
| --- | -------- | ----------- | --- | --- | --- |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
|     |          |             |     |     |     |
                    Información acerca del importe total de un tipo particular de
./cac:TaxTotal
                        impuesto. Una repetición por IGV, ISC, Otros tributos  1..n
                 ./cbc:TaxAmount@currencyID  Monto total del tributo del ítem (IGV, ISC, Otros tributos)  1
|     |       |             |                    |     |     |
| --- | ----- | ----------- | ------------------ | --- | --- |
|     |       |             | ./cac:TaxSubtotal  |     | 1   |
                      ./cbc:TaxAmount@currencyID  Monto total del tributo del ítem (IGV, ISC, Otros tributos)  1
|     |     |       |     |     |     |
| --- | --- | ----- | --- | --- | --- |
            ./cac:TaxCategory/cac:TaxScheme/cbc:ID  Código del tributo según catálogo SUNAT  1
|     |     |       |     |     |     |
| --- | --- | ----- | --- | --- | --- |
            ./cac:TaxCategory/cac:TaxScheme/cbc:Name  Nombre del Tributo  1
|     |     |       |     |     |     |
| --- | --- | ----- | --- | --- | --- |
                        ./cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode  Código internacional del Tributo   0..1
|      |     |     |     |     |     |
| ---- | --- | --- | --- | --- | --- |
- 10 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
1.3 Estructura del resumen diario vs Formato XML
N° INFORMACIÓN DEL DOCUMENTO
1 Versión del UBL utilizado para establecer el formato XML
<cbc:UBLVersionID>2.0</ cbc:UBLVersionID >
2 Versión de la estructura del documento
<cbc:CustomizationID>1.1</ cbc:CustomizationID >
3 Identificador del resumen
<cbc: ID>RC-20171227-00001</ cbc: ID >
4 Fecha de generación del resumen
<cbc:IssueDate> 2017-12-28</ cbc:IssueDate >
5 Fecha de emisión de los documentos
<cbc:ReferenceDate>2017-12-27</ cbc:ReferenceDate >
6 Firma Digital
<ext:UBLExtensions>
<ext:UBLExtension>
<ext:ExtensionContent>
<ds:Signature Id="IDFirma">
<ds:SignedInfo>
<ds:CanonicalizationMethod
Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments"/>
<ds:SignatureMethod
Algorithm="http://www.w3.org/2000/09/xmldsig#dsa-
sha1"/>
<ds:Reference URI="">
<ds:Transforms>
<ds:Transform
Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
</ds:Transforms>
<ds:DigestMethod
Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
<ds:DigestValue>+pruib33lOapq6GSw58GgQLR8VGIGqANloj4EqB1cb4=</ds:DigestValue>
</ds:Reference>
</ds:SignedInfo>
<ds:SignatureValue>…Oatv5xMfFIn….</ds:SignatureValue>
<ds:KeyInfo>
<ds:X509Data>
<ds:X509Certificate>….MIIF9TCCBN2gAw…</ds:X509Certificate>
</ds:X509Data>
</ds:KeyInfo>
</ds:Signature>
</ext:ExtensionContent>
</ext:UBLExtension>
</ext:UBLExtensions>
…
...
<cac:Signature>
<cbc:ID>IDFirma</cbc:ID>
<cac:SignatoryParty>
<cac:PartyIdentification>
- 11 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:ID>20100113612</cbc:ID>
</cac:PartyIdentification>
<cac:PartyName>
<cbc:Name>Empresa emisora</cbc:Name>
</cac:PartyName>
</cac:SignatoryParty>
<cac:DigitalSignatureAttachment>
<cac:ExternalReference>
<cbc:URI>#signatureKG</cbc:URI>
</cac:ExternalReference>
</cac:DigitalSignatureAttachment>
</cac:Signature>
7 Número de RUC
8 Apellidos y nombres o denominación o razón social
<cac:AccountingSupplierParty>
<cbc:CustomerAssignedAccountID>20100113612</cbc:CustomerAssignedAccountID>
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
<cac:Party>
<cac:PartyLegalEntity>
<cbc:RegistrationName>Empresa emisora</cbc:RegistrationName>
</cac:PartyLegalEntity>
</cac:Party>
</cac:AccountingSupplierParty>
9 Número de fila
10 Serie y número de comprobante de pago
11 Tipo de comprobante de pago
<sac:SummaryDocumentsLine >
…
<cbc:LineID>1</cbc:LineID>
<cbc:ID>BP01-00298341</cbc: ID>
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
…
</sac:SummaryDocumentsLine >
12 Número de documento de identidad del adquiriente o usuario
13 Tipo de documento de identidad del adquiriente o usuario
<sac:SummaryDocumentsLine >
…
<cac:AccountingCustomerParty>
<cbc:CustomerAssignedAccountID>20100113612</cbc:CustomerAssignedAccountID>
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
…
</sac:SummaryDocumentsLine >
14 Serie y Número de la boleta de venta que modifica
15 Tipo de documento que modifica
<sac:SummaryDocumentsLine >
…
<cac:BillingReference>
<cac:InvoiceDocumentReference>
<cbc:ID>BF02-00298303</ID>
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
</cac:InvoiceDocumentReference>
</cac:BillingReference>
- 12 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
…
</sac:SummaryDocumentsLine >
16 Régimen de percepción
17 Tasa de la percepción
18 Monto de la percepción
19 Monto Total incluida la percepción
20 Base imponible de la percepción
<sac:SummaryDocumentsLine >
…
< sac:SUNATPerceptionSummaryDocumentReference>
<sac:SUNATPerceptionSystemCode>01</sac:SUNATPerceptionSystemCode>
<sac:SUNATPerceptionPercent>2.00</sac:SUNATPerceptionPercent>
<cbc:TotalInvoiceAmount currencyID="PEN">4.00</cbc:TotalInvoiceAmount>
<sac:SUNATTotalCashed>204.00</sac:SUNATTotalCashed>
<cbc:TaxableAmount currencyID="PEN">200.00</cbc:TaxableAmount>
</sac:SUNATPerceptionSummaryDocumentReference>
…
</sac:SummaryDocumentsLine >
21 Estado del ítem
<sac:SummaryDocumentsLine >
…
<cac:Status>
<cbc:ConditionCode>1</cbc: ConditionCode>
</cac:Status>
…
</sac:SummaryDocumentsLine >
22 Importe total de la venta, cesión en uso o del servicio prestado
<sac:SummaryDocumentsLine >
…
<sac: TotalAmount currencyID="PEN">204.00</sac:TotalAmount>
…
</sac:SummaryDocumentsLine >
23 Total valor de venta - operaciones gravadas
24 Total valor de venta - operaciones exoneradas
25 Total valor de venta - operaciones inafectas
26 Total valor de venta - operaciones gratuitas
<sac:SummaryDocumentsLine >
…
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">197.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<sac:BillingPayment>
<cbc:PaidAmount currencyID ="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<sac:BillingPayment>
<cbc:PaidAmount currencyID = "PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
- 13 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<sac:BillingPayment>
<cbc:PaidAmount currencyID ="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>05</cbc:InstructionID>
</sac:BillingPayment>
...
</sac:SummaryDocumentsLine >
27 Importe total de sumatoria otros cargos del ítem
</sac:SummaryDocumentsLine >
…
<cac:AllowanceCharge>
<cbc:ChargeIndicator>true</cbc:ChargeIndicator>
<cbc:Amount currencyID="PEN">0.00</cbc:Amount>
</cac:AllowanceCharge>
…
</sac:SummaryDocumentsLine >
28 Total ISC
29 Total IGV
30 Total Otros tributos
<sac:SummaryDocumentsLine >
…
<cac:TaxTotal>
<cbc:TaxAmount currencyID ="PEN">56.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxAmount currencyID ="PEN">56.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID>2000</cbc:ID>
<cbc:Name>ISC</cbc:Name>
<cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<cac:TaxTotal>
<cbc:TaxAmount currencyID ="PEN">76.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxAmount currencyID ="PEN">76.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<cac:TaxTotal>
<cbc:TaxAmount currencyID ="PEN">12.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxAmount currencyID ="PEN">12.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID>9999</cbc:ID>
- 14 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:Name>OTROS</cbc:Name>
<cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
< /cac:TaxSubtotal>
</cac:TaxTotal>
…
</sac:SummaryDocumentsLine >
- 15 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
1.4 Estructura del resumen diario según el estándar UBL 2.0
A) Normas de uso
El formato UBL está basado en el uso de un documento XML para presentar todos los
datos de forma jerárquica; para ello, debe existir un único tag que engloba a todos los
demás, dicha etiqueta para el caso del resumen diario es SummaryDocument.
<SummaryDocuments >
......
</ SummaryDocuments >
Para un mejor entendimiento de la estructura del archivo XML, se detalla por cada
elemento, en el cual se indica si es “mandatorio o condicional”, y se indica que campo
corresponde con los requisitos o contenidos del resumen diario de boletas de venta y notas
electrónicas asociadas.
A.1) Elementos del resumen diario de boletas de venta electrónicas y notas
electrónicas
A continuación se detallan los elementos que forman parte del resumen diario de boletas
de venta electrónicas y notas electrónicas.
1. Versión del UBL utilizado para establecer el formato XML (Mandatorio)
Versión UBL 2.0.
Ubicación
/SummaryDocuments/cbc:UBLVersionID
2. Versión de la estructura del documento (Mandatorio)
Versión 1.1 de la estructura del documento.
Ubicación
/SummaryDocuments/cbc:CustomizationID
3. Identificador del resumen (Mandatorio)
El formato es el siguiente: <RC>-<Fecha de emisión de los comprobantes de pago
YYYYMMDD>. Por ejemplo, si el resumen corresponde a los comprobantes
emitidos el 23/01/2018, el identificador debe ser: RC-20180123
Ubicación
/SummaryDocuments/cbc:ID
- 16 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
4. Fecha de generación del resumen (Mandatorio)
Fecha en la cual se generó el resumen diario de boletas de venta electrónicas y notas
electrónicas. El tipo DateType corresponde al tipo Date del XML, el formato deberá ser
yyyy-mm-dd.
La fecha de generación del resumen no podrá ser menor a la fecha de emisión de los
documentos informados.
Ubicación
/SummaryDocuments/cbc:IssueDate
5. Fecha de emisión de los documentos (Mandatorio)
Corresponde a la fecha de emisión de las boletas de venta electrónicas y notas
electrónicas, contenidas en el resumen diario.
El Resumen diario de boletas de venta y notas se podrá incluir uno o más documentos,
siempre que todos hayan sido generados o emitidos en un mismo día.
Ubicación
/SummaryDocuments/cbc:ReferenceDate
6. Firma Digital (Mandatorio)
Es el conjunto de datos asociados al documento electrónico que se firma y a su vez,
permite la identificación del signatario, (emisor del resumen diario). Ha sido creada por
medios que éste mantiene bajo su control, de manera que está vinculada únicamente
al signatario y a los datos a los que refiere.
La firma deberá realizarse con el certificado digital que el emisor informó previamente a
la SUNAT.
Ubicación
/SummaryDocuments/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature
/SummaryDocuments/cac:Signature
7. Número de RUC (Mandatorio)
Corresponde al número de RUC del emisor electrónico. El número de RUC debe ser
válido.
Ubicación
/SummaryDocuments/cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID (Número
de RUC)
/SummaryDocuments/cac:AccountingSupplierParty/cbc:AdditionalAccountID (Tipo de documento
- Catálogo No. 06)
- 17 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
8. Apellidos y nombres o denominación o razón social (Mandatorio)
Corresponde a los apellidos y nombres o denominación o razón social del emisor
electrónico. Esta información debe ser igual a lo registrado en el Registro Único de
Contribuyentes - RUC.
Ubicación
/SummaryDocuments/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:Registrati
onName
9. Número de fila (Mandatorio)
Número de orden de la línea referenciada en el resumen diario de boletas de venta
electrónicas y notas electrónicas. Cada comprobante a informar se consigna en una
línea del resumen diario.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cbc:LineID
10. Número de serie del documento – Numero correlativo (Mandatorio)
Número de serie y número correlativo del documento que se informa en la línea del
resumen diario. Todos los comprobantes informados deben corresponder a una sola
fecha de emisión.
La serie debe ser alfanumérica de 4 caracteres, siendo el primer carácter de la
izquierda la letra “B”, como por ejemplo: B001, el número correlativo podrá tener hasta
(8) caracteres numéricos.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cbc:ID
11. Tipo de documento (Mandatorio)
Es el tipo de comprobante de pago que se está informando. Se usará el catálogo
N° 01: “Código de Tipo de documento”.
- 18 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
Para el resumen diario, solo se podrán informar los siguientes comprobantes de
pago:
03 Boleta de venta electrónica
07 Nota de crédito
08 Nota de débito
No se informara en el resumen diario las siguientes notas:
Nota de crédito tipo 10 “Otros conceptos”
Nota de debito tipo 03 “Penalidad/Otros conceptos”
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cbc:DocumentTypeCode
12. Número de documento de Identidad del adquirente o usuario (Mandatorio)
Se consignará el número de documento de identidad del adquiriente o usuario si el
importe total de la boleta de venta supera la suma de setecientos nuevos soles (S/
700.00). Si el monto es menor al indicado, y no se cuenta con la información, deberá
consignarse guión (“-“).
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:AccountingCustomerParty/cbc:Customer
AssignedAccountID
13. Tipo de documento de Identidad del adquirente o usuario (Mandatorio)
Se consignará el tipo de documento de identidad del adquiriente o usuario si el importe
total de la boleta de venta supera la suma de setecientos nuevos soles (S/ 700.00). Si
el monto es menor al indicado, y no se cuenta con la información, deberá consignarse
guión (“-“).
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:AccountingCustomerParty/cbc:Additiona
lAccountID
14. Número de serie de la boleta de venta que modifica - Número correlativo
de la boleta de venta que modifica (Condicional)
Las notas de crédito y/o débito a informar en el Resumen son aquellas vinculadas a
las boletas de venta o tickets de máquina registradora.
Para boleta de venta: Se consignara en el presente campo el número de serie y el
número correlativo del comprobante.
Para ticket de máquina registradora: Se consignara la serie de la máquina registradora
y el número correlativo del comprobante. De no contar con la información de la serie
del ticket se consignarse guión (“-“).
- 19 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
Ubicación:
/SummaryDocuments/sac:SummaryDocumentsLine/cac:BillingReference/cac:InvoiceDocumentR
eference/cbc:ID
15. Tipo de documento que modifica (Condicional)
En el resumen diario, se podrá modificar los tipos de comprobantes de pago
siguientes:
03 Boleta de venta
12 Ticket de máquina registradora
Ubicación:
/SummaryDocuments/sac:SummaryDocumentsLine/cac:BillingReference/cac:InvoiceDocumentR
eference/cbc:DocumentTypeCode
16. Régimen de percepción (Condicional)
Se usará el catálogo N° 22 “Regímenes de Percepción”
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:SUNATPerceptionSummaryDocumentR
eference/sac:SUNATPerceptionSystemCode
- 20 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
17. Tasa de la percepción (Condicional)
Se usará el catálogo N° 22 “Regímenes de Percepción”
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:SUNATPerceptionSummaryDocumentR
eference/sac:SUNATPerceptionPercent
18. Monto de la percepción (Condicional)
Es el resultado del Importe total de la venta o de la base imponible de la percepción
multiplicada por la tasa de percepción.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:SUNATPerceptionSummaryDocumentR
eference/cbc:TotalInvoiceAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
19. Monto total a cobrar incluida la percepción (Condicional)
En el caso de venta interna e hidrocarburos, es el resultado del Importe total de la
venta más el monto de la percepción.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:SUNATPerceptionSummaryDocumentR
eference/sac:SUNATTotalCashed
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
- 21 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
20. Base imponible percepción (Condicional)
En el caso de venta interna e hidrocarburos, es el importe total de venta sobre el cual
se aplica la tasa de la percepción.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:SUNATPerceptionSummaryDocumentR
eference/cbc:TaxableAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
21. Estado del ítem (Mandatorio)
Se usará el Catalogo N° 19 “Resumen diario de boletas de venta y notas electrónicas-
Códigos de estado de ítem.
Para tal efecto se deberá considerar lo siguiente:
o Adicionar: Se usará para nuevos documentos que no han sido informados
o Modificar: Debe utilizarse en el supuesto de documentos ya informados sobre los
que se modificará algún dato. No es modificable el tipo, serie y número del
documento informado.
o Anulado: Aplicable a documentos informados que no ha sido otorgado al
adquiriente o usuario, de lo contrario la anulación de la operación deberá
sustentarse mediante Nota de Crédito.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:Status/cbc:ConditionCode
22. Importe total de la venta, cesión en uso o del servicio prestado
(Mandatorio)
- 22 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
Corresponde a la sumatoria de los elementos: Total valor de venta - operaciones
gravadas, Total valor de venta – Inafectas, Total valor de venta – Exoneradas, Total
IGV, Total ISC, Importe total de sumatoria otros cargos del item y Total otros tributos
En la operaciones gratuitas se consignara en el elemento el valor cero (0.00).
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:TotalAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
23. Total valor de venta - operaciones gravadas (Mandatorio)
Sumatoria del valor de venta de las operaciones gravadas con el IGV. Monto que
incluye la deducción de descuentos, si los hubiese.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:BillingPayment/cbc:PaidAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
24. Total valor de venta - operaciones exoneradas (Mandatorio)
Sumatoria del valor de venta de las operaciones exoneradas con el IGV. Monto que
incluye la deducción de descuentos, si los hubiese.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:BillingPayment/cbc:PaidAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
25. Total valor de venta - operaciones inafectas (Mandatorio)
Sumatoria del valor de venta de las operaciones inafectas con el IGV incluidas las
exportaciones. Monto que incluye la deducción de descuentos, si los hubiese.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:BillingPayment/cbc:PaidAmoun
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
26. Total Valor Venta operaciones Gratuitas (Condicional)
Sumatoria del valor de venta de las operaciones gratuitas. Monto que incluye la
deducción de descuentos, si los hubiese.
- 23 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/sac:BillingPayment/cbc:PaidAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
27. Importe total de sumatoria otros cargos del ítem (Condicional)
Sumatoria de otros cargos, consignados en el comprobante de pago y que no forman
parte del valor de venta.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:AllowanceCharge/cbc:Amount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
28. Total ISC (Condicional)
Sumatoria del ISC de las operaciones comprendidas en el comprobante de pago.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cbc:TaxAmount
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmou
nt
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
29. Total IGV (Mandatorio)
Sumatoria del IGV de las operaciones gravadas comprendidas en el comprobante de
pago.
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cbc:TaxAmount
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
30. Total Otros tributos (Condicional)
Sumatoria de otros tributos (distinto al IGV, ISC o IVAP) comprendidos en el
comprobante de pago
Ubicación
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cbc:TaxAmount
/SummaryDocuments/sac:SummaryDocumentsLine/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmou
nt
Al tratarse de un monto, obligatoriamente se deberá consignar el tipo de moneda en el
atributo @currencyID
- 24 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas

B) Ejemplo: Resumen de boletas de venta electrónicas

|   REQUISITO  |     |     |     | EJEMPLO  |     |     |     |     |     |     |
| ------------ | --- | --- | --- | -------- | --- | --- | --- | --- | --- | --- |
   Apellidos y Nombres       CORPORACION A  MERICA S. A .
|  Número de RUC  |    20480362872  |     |     |     |     |     |     |     |     |     |
| --------------- | --------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
|                 |                 |     |     |     |     |     |     |     |     |     |
Fecha de emisión de los
   18-dic-17
comprobantes
|                     |     |     |     |     |     |     |     |     |     |     |
| ------------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
|  Número de fila     |     | 1   | 2   | 3   |     | 4   |     | 5   | 6   | 7   |
|  Tipo de documento  |     | 03  | 03  | 07  |     | 03  |     | 03  | 03  | 03  |

|  Número de serie   |     | B1A5  | B1A5  | BC20  |     | B1A5  | B1A5  |     | B1A5  | B1A5  |
| ------------------ | --- | ----- | ----- | ----- | --- | ----- | ----- | --- | ----- | ----- |

 Número de documento     1101001  1101002  171872  1101004  1101005  1101006  1101007
|  Tipo doc. Adquiriente  |              | 6   | 6            | 1         |              | 6   |           | 1   |     | 6        |
| ----------------------- | ------------ | --- | ------------ | --------- | ------------ | --- | --------- | --- | --- | -------- |
|                         |              |     |              |           |              |     |           |     |     | 10676663 |
|                         | 10098237761  |     | 10401308487  | 09728737  | 10304567812  |     | 72670972  |     |     |          |
|  Núm. Doc. Adquiriente  |              |     |              |           |              |     |           |     |     | 376      |
| Estado del ítem         |              | 1   | 1            | 1         |              | 1   |           | 1   | 1   | 1        |
|                         |              |     |              |           |              |     |           |     |     |          |
 Importe total de la venta
  3,207.00  1,044.00  1,416.00  3,855.00  0.00  345.00  1,475.00
Total valor de venta –
|     |     | 1,500.00  | 800.00  | 1,200.00  |     | 2000.00  |     |     |     | 1,250.00  |
| --- | --- | --------- | ------- | --------- | --- | -------- | --- | --- | --- | --------- |
operac. gravadas
|     |     |     |     |     |     |     |     |     |     |     |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
Total valor de venta –
|                       |     |     | 100.00  |     |     | 1000.00  |     |     |     |     |
| --------------------- | --- | --- | ------- | --- | --- | -------- | --- | --- | --- | --- |
|  operac.  exoneradas  |     |     |         |     |     |          |     |     |     |     |
Total valor de venta –
|  operac. inafectas |     | 232.00  |     |     |     |     |     |     | 345.00  |     |
| ------------------ | --- | ------- | --- | --- | --- | --- | --- | --- | ------- | --- |

Total valor de venta –
|                           |     |       |     |     |     |         | 250.00  |     |     |     |
| ------------------------- | --- | ----- | --- | --- | --- | ------- | ------- | --- | --- | --- |
|  operac. gratuitas        |     |       |     |     |     |         |         |     |     |     |
|  Importe total de cargos  |     | 5.00  |     |     |     | 345.00  |         |     |     |     |
 Total IGV    270.00  144.00  216.00  360.00  0.00  0.00  225.00
|  Total ISC              |     |           |     |     |     | 150.00  |     |     |     |     |
| ----------------------- | --- | --------- | --- | --- | --- | ------- | --- | --- | --- | --- |
|  Total otros tributos   |     | 1,200.00  |     |     |     |         |     |     |     |     |
|  Tipo de doc. modifica  |     |           |     | 03  |     |         |     |     |     |     |

|  Serie de doc. modifica  |     |     |     | B1A5  |     |     |     |     |     |     |
| ------------------------ | --- | --- | --- | ----- | --- | --- | --- | --- | --- | --- |

|  Núm. de doc. modifica  |     |     |     | 1100992  |     |     |     |     |     |     |
| ----------------------- | --- | --- | --- | -------- | --- | --- | --- | --- | --- | --- |

|  Régimen de percepción     |     |     |     |     |     |     |     |     |     | 01        |
| -------------------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --------- |
|  Tasa de percepción        |     |     |     |     |     |     |     |     |     | 2%        |
|  Monto de percepción       |     |     |     |     |     |     |     |     |     | 29.50     |
|  Monto total inc. Percep.  |     |     |     |     |     |     |     |     |     | 1,504.50  |
|  Base imponible percepc.   |     |     |     |     |     |     |     |     |     | 1,475.00  |

|  Identificador del resumen    RC-20171218-900  |               |     |     |     |     |     |     |     |     |     |
| ---------------------------------------------- | ------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
|  Fecha de generación                           |    18-dic-17  |     |     |     |     |     |     |     |     |     |

| Firma digital  |     |     |     |     |     |     |     |     |     |     |
| -------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

| Versión del UBL utilizado          |     |     |     |     |     |     |     |     |     |     |
| ---------------------------------- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| para establecer el formato    2.0  |     |     |     |     |     |     |     |     |     |     |
 XML

Versión de la estructura
|     |    1.1  |     |     |     |     |     |     |     |     |     |
| --- | ------- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
del documento

- 25 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<?xml version="1.0" encoding="UTF-8"?>
<p:SummaryDocuments
xmlns:p="urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1"
xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
xmlns:sac="urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1>
<ext:UBLExtensions>
<ext:UBLExtension>
<ext:ExtensionContent><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
Id="SRC-20171218-900"><ds:SignedInfo><ds:CanonicalizationMethod
Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><ds:SignatureMethod
Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><ds:Reference
URI=""><ds:Transforms><ds:Transform
Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-
signature"/></ds:Transforms><ds:DigestMethod
Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><ds:DigestValue>LV6bfOoAplGpr4sNmE5Va
waaFgE=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>WpfJ+u10t/f9lq3
OciN1vBd2Pf6FhwoHmos0SRjruhXNuvuRUn+B96sXw8r7UzbfrDqtWXD/BBu4tvzElS/uE8DoT5G7TWoVh5l5qk6l
J0Q/i8cqd7B7FXrqLv+K2VahbHzKemnlD9z50zfKgjibe1w0YlH5KS7j9CgqT6mJONw=</ds:SignatureValue><
ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIC4zCCAkygAwIBAgIEWL33HTANBgkqhkiG9w0BAQUFA
DCBmjEjMCEGCSqGSIb3DQEJARYUbnF1aXNwZUBzdW5hdC5nb2IucGUxCzAJBgNVBAYTAlBFMQ0wCwYDVQQIDARMaW
1hMQ0wCwYDVQQHDARMaW1hMRgwFgYDVQQKDA9ORUNTU0VJTiBTLlIuTC4xGDAWBgNVBAsMD05FQ1NTRUlOIFMuUi5
MLjEUMBIGA1UEAwwLMjA0NTI2MjY2OTIwHhcNMTcwMzA2MjM1NjEzWhcNMjAwMzA1MjM1NjEzWjCBmjEjMCEGCSqG
SIb3DQEJARYUbnF1aXNwZUBzdW5hdC5nb2IucGUxCzAJBgNVBAYTAlBFMQ0wCwYDVQQIDARMaW1hMQ0wCwYDVQQHD
ARMaW1hMRgwFgYDVQQKDA9ORUNTU0VJTiBTLlIuTC4xGDAWBgNVBAsMD05FQ1NTRUlOIFMuUi5MLjEUMBIGA1UEAw
wLMjA0NTI2MjY2OTIwgZ8wDQYJKoZIhvcNAQEBBQADgY0AMIGJAoGBAJwxYaUeHNIB6LCCiUdb9PTecWFT6xihrT5
R2JHr2nlblFzY7NeVeYhMvNGpKUUZTAeYayyFr20WlxUPu08ZwQgI0X8GsYF0Kd6N4/muh9VMGIj+2ltBygupYLha
TprwRoZr1xrwBQCZkueJizxAVfZ2xZxDFrKSY+vjLRpweaLRAgMBAAGjNDAyMCAGA1UdJQEB/wQWMBQGCCsGAQUFB
wMBBggrBgEFBQcDAjAOBgNVHQ8BAf8EBAMCBPAwDQYJKoZIhvcNAQEFBQADgYEAPcYS10B2LlFhIITGmyxe+Gm5Rc
+jO7O/KPYWHVAuuYJOggIsSzjRSkDYkwhRooBT0yAsqYOdbOOgPOwUyfeEFxsyqNoaGR0rUD/enevV/jGCSF9sdmP
6UPRxIsZQVQoO/DKsi391Ht8gwlhlsDFZkWE14LvKyPx39chaV02mvOw=</ds:X509Certificate></ds:X509Da
ta></ds:KeyInfo></ds:Signature>
</ext:ExtensionContent>
</ext:UBLExtension>
</ext:UBLExtensions>
<!-- Version del UBL utilizado para establecer el formato XML -->
<cbc:UBLVersionID>2.0</cbc:UBLVersionID>
<!-- Version de la estructura del documento -->
<cbc:CustomizationID>1.1</cbc:CustomizationID>
<!-- Identificador del resumen -->
<cbc:ID>RC-20171218-900</cbc:ID>
<!-- Fecha de generacion del resumen -->
<cbc:ReferenceDate>2017-12-18</cbc:ReferenceDate>
<!-- Fecha de emision de los documentos -->
<cbc:IssueDate>2017-12-18</cbc:IssueDate>
<cbc:Note><![CDATA[CONSOLIDADO DE BOLETAS DE VENTA]]></cbc:Note>
<cac:Signature>
<cbc:ID>SRC-20171218-900</cbc:ID>
<cac:SignatoryParty>
<cac:PartyIdentification>
<cbc:ID>20480072872</cbc:ID>
</cac:PartyIdentification>
<cac:PartyName>
<cbc:Name><![CDATA[SUNAT]]></cbc:Name>
</cac:PartyName>
</cac:SignatoryParty>
<cac:DigitalSignatureAttachment>
<cac:ExternalReference>
<cbc:URI>#SRC-20171218-900</cbc:URI>
</cac:ExternalReference>
</cac:DigitalSignatureAttachment>
</cac:Signature>
<cac:AccountingSupplierParty>
<!-- Numero de RUC -->
- 26 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:CustomerAssignedAccountID>20480072872</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
<cac:Party>
<cac:PartyLegalEntity>
<!-- Apellidos y nombres o denominacion o razon social -->
<cbc:RegistrationName><![CDATA[CORPORACION AMERICA
S.A.]]></cbc:RegistrationName>
</cac:PartyLegalEntity>
</cac:Party>
</cac:AccountingSupplierParty>
<sac:SummaryDocumentsLine>
<cbc:LineID>1</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101001</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>10098237761</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado -->
<sac:TotalAmount currencyID="PEN">3207.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">1500.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">232.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Importe total de sumatoria otros cargos del item -->
<cac:AllowanceCharge>
<cbc:ChargeIndicator>true</cbc:ChargeIndicator>
<cbc:Amount currencyID="PEN">5.00</cbc:Amount>
</cac:AllowanceCharge>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">270.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">270.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
- 27 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<!-- Total Otros tributos -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">1200.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">1200.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>9999</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>OTROS</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<sac:SummaryDocumentsLine>
<cbc:LineID>2</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101002</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>10401308487</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado (Precio incluye
todos los impuestos) -->
<sac:TotalAmount currencyID="PEN">1044.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">800.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">100.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">144.00</cbc:TaxAmount>
<cac:TaxSubtotal>
- 28 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">144.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<sac:SummaryDocumentsLine>
<cbc:LineID>3</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>07</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>BC20-171872</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>09728737</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>1</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:BillingReference>
<cac:InvoiceDocumentReference>
<!-- Serie y numero de comprobante modificado -->
<cbc:ID>B1A5-1100992</cbc:ID>
<!-- Tipo de comprobante modificado - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
</cac:InvoiceDocumentReference>
</cac:BillingReference>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado (Precio incluye
todos los impuestos) -->
<sac:TotalAmount currencyID="PEN">1416.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">1200.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">216.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
- 29 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:TaxAmount currencyID="PEN">216.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<sac:SummaryDocumentsLine>
<cbc:LineID>4</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101004</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>10304567812</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado (Precio incluye
todos los impuestos) -->
<sac:TotalAmount currencyID="PEN">3855.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">2000.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">1000.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Importe total de sumatoria otros cargos del item -->
<cac:AllowanceCharge>
<cbc:ChargeIndicator>true</cbc:ChargeIndicator>
<cbc:Amount currencyID="PEN">345.00</cbc:Amount>
</cac:AllowanceCharge>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">360.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">360.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
- 30 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<!-- Es una operacion Gratuita -->
<sac:SummaryDocumentsLine>
<cbc:LineID>5</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101005</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>72670972</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>1</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado (Precio incluye
todos los impuestos) -->
<sac:TotalAmount currencyID="PEN">0.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor venta - operaciones gratuitas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">250.00</cbc:PaidAmount>
<!-- (Codigo de tipo de valor de venta - Catalogo No 11) -->
<cbc:InstructionID>05</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
- 31 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<!-- Operaciones inafectas y exoneradas -->
<sac:SummaryDocumentsLine>
<cbc:LineID>6</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101006</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>-</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>-</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<cac:Status>
<!-- (Codigo de operacion del item - catalogo No. 19) -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado (Precio incluye
todos los impuestos) -->
<sac:TotalAmount currencyID="PEN">345.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">345.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
- 32 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
<sac:SummaryDocumentsLine>
<cbc:LineID>7</cbc:LineID>
<!-- Tipo de documento - Catalogo No. 01 -->
<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>
<!-- Serie y número de comprobante -->
<cbc:ID>B1A5-1101007</cbc:ID>
<cac:AccountingCustomerParty>
<!-- Numero de documento de identidad -->
<cbc:CustomerAssignedAccountID>10676663376</cbc:CustomerAssignedAccountID>
<!-- Tipo de documento de identidad - Catalogo No. 06 -->
<cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
</cac:AccountingCustomerParty>
<!-- Informacion de percepcion -->
<sac:SUNATPerceptionSummaryDocumentReference>
<!-- Regimen de percepcion -->
<sac:SUNATPerceptionSystemCode>01</sac:SUNATPerceptionSystemCode>
<!-- Valor del procentaje rango 0 a 100 de acuerdo al SUNATPerceptionSystemCode -->
<sac:SUNATPerceptionPercent>2.0</sac:SUNATPerceptionPercent>
<!-- Monto total de la percepcion-->
<cbc:TotalInvoiceAmount currencyID="PEN">29.50</cbc:TotalInvoiceAmount>
<!-- Monto total a cobrar incluida la percepcion tiene que ser mayor que cero -->
<sac:SUNATTotalCashed currencyID="PEN">1504.50</sac:SUNATTotalCashed>
<!-- Base imponible percepcion -->
<cbc:TaxableAmount currencyID="PEN">1475.00</cbc:TaxableAmount>
</sac:SUNATPerceptionSummaryDocumentReference>
<cac:Status>
<!-- Codigo de operacion del item - catalogo No. 19 -->
<cbc:ConditionCode>1</cbc:ConditionCode>
</cac:Status>
<!-- Importe total de la venta, cesion en uso o del servicio prestado -->
<sac:TotalAmount currencyID="PEN">1475.00</sac:TotalAmount>
<!-- Total valor de venta - operaciones gravadas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">1250.00</cbc:PaidAmount>
<cbc:InstructionID>01</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones exoneradas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>02</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total valor de venta - operaciones inafectas -->
<sac:BillingPayment>
<cbc:PaidAmount currencyID="PEN">0.00</cbc:PaidAmount>
<cbc:InstructionID>03</cbc:InstructionID>
</sac:BillingPayment>
<!-- Total IGV -->
<cac:TaxTotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">225.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<!-- Monto Total y Moneda -->
<cbc:TaxAmount currencyID="PEN">225.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<!-- Codigo de tributo - Catalogo No. 05 -->
<cbc:ID>1000</cbc:ID>
<!-- Nombre de tributo - Catalogo No. 05 -->
<cbc:Name>IGV</cbc:Name>
<!-- Codigo internacional tributo - Catalogo No. 05 -->
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
- 33 -

Guía de elaboración de Resumen diario de boletas de venta y notas electrónicas
</cac:TaxSubtotal>
</cac:TaxTotal>
</sac:SummaryDocumentsLine>
<p:/SummaryDocuments>
- 34 -