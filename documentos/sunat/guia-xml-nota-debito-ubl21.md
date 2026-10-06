Guía de Elaboración de
Documentos XML
Nota de Débito Electrónica
UBL 2.1
PROYECTO DE COMPROBANTES DE PAGO
ELECTRONICOS
Versión 1.0
Mayo 2017

Guía de elaboración de documentos electrónicos XML - UBL 2.1
INDICE
1 NOTA DE DÉBITO ELECTRONICA ................................................................ 3
1.1 Requisitos de la Nota de Débito electrónica .................................................... 3
1.2 Estructura de Nota de débito vs FormatoXML ................................................. 9
1.3 Normas de Uso del Formato de la Nota de Débito Electrónica...................... 13
1. Firma Digital ...................................................................................................... 14
2. Versión del UBL ................................................................................................ 16
3. Versión de la estructura del documento............................................................. 16
4. Numeración, conformada por serie y número correlativo ................................... 17
5. Fecha de emisión .............................................................................................. 17
6. Leyendas ........................................................................................................... 18
7. Tipo de moneda en la cual se emite la nota de débito electrónica ..................... 18
8. Código del tipo de Nota de débito electrónica ................................................... 19
9. Motivo o Sustento .............................................................................................. 20
10. Serie y número del documento que modifica ..................................................... 20
11. Tipo de documento del documento quemodifica ................................................ 21
12. Documento dereferencia ................................................................................... 21
13. Apellidos y nombres o denominación o razón social.......................................... 22
14. Tipo y Número de RUC del Emisor. ................................................................... 23
15. Código del domicilio fiscal o de local anexo del emisor. .................................... 24
16. Apellidos y nombres o denominación o razón social del adquirente o usuario. .. 25
17. Tipo y número de documento de identidad del adquirente o usuario. ................ 25
18. Monto Total de Impuestos. ................................................................................ 26
19. Total valor de venta - operaciones gravadas. .................................................... 26
20. Total valor de venta - operaciones inafectas. .................................................... 27
21. Total valor de venta - operaciones exoneradas. ................................................ 29
22. Sumatoria de IGV. ............................................................................................. 30
23. Sumatoria de ISC. ............................................................................................. 30
24. Sumatoria de Otros Tributos. ............................................................................. 31
25. Total de Descuentos. ......................................................................................... 32
26. Importe total de la venta, de la cesión en uso o del servicio prestado. .............. 32
27. Número de orden del Ítem. ................................................................................ 33
28. Cantidad de unidades por ítem. ......................................................................... 33
29. Valor de venta por ítem. .................................................................................... 34
30. Precio de Venta unitario por ítem que modifica y código. .................................. 34
31. Valor unitario por ítem en operaciones no onerosas y código. ........................... 35
Nota de Débito Electrónica ~ 1 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
32. Afectación al IGV del ítem que modifica. ........................................................... 36
33. Afectación al ISC del ítem que modifica. ........................................................... 36
34. Descripción detallada del servicio prestado, bien vendido o cedido en uso. ...... 37
35. Código Producto. ............................................................................................... 38
36. Código Producto de SUNAT. ............................................................................. 38
37. Valor unitario del ítem. ....................................................................................... 39
B.2 Detalle de elementos complejos ........................................................................... 40
B.2.1 Tag UBLExtension .......................................................................................... 40
1.5 Ejemplos de casos identificados ................................................................... 44
Nota de Débito Electrónica ~ 2 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
1 NOTA DE DÉBITO ELECTRONICA

La  nota  de  débito  electrónica  es  la  nota  de  débito  regulada  por  el  Reglamento  de
Comprobantes  de  pago  soportada  en  un  formato  digital  que  cumple  con  las
especificaciones  reguladas  en  la  R.S.097-2012/SUNAT  y  que  se  encuentra  firmada
digitalmente.

El contenido de información ha sido regulado por el Anexo 04 de la referida Resoluion de
Superintendencia y por el Anexo 09 en relación al uso del estándar UBL. En el presente
documento se desarrolla el detalle de los campos (tag) indicados en dicho anexo.

1.1  Requisitos de la Nota de Débito electrónica
En  el  cuadro  siguiente,  se  describe  el  contenido  (campos)  de  la  Nota  de  Credito
electrónica. Para lo cual, de manera previa, es necesario establecer la nomenclatura de
representación del valor de los datos, para una comprensión correcta delcontenido

| a  caracter alfabético                             |     |     |     |     |
| -------------------------------------------------- | --- | --- | --- | --- |
| n  caracter numérico                               |     |     |     |     |
| an  carácter alfanumérico                          |     |     |     |     |
| a3  3 caracteres alfabéticos de longitud fija      |     |     |     |     |
| n3  3 caracteres numéricos de longitud fija        |     |     |     |     |
| an3  3 caracteres alfa-numéricos de longitud fija  |     |     |     |     |
| a..3  hasta 3 caracteres alfabéticos               |     |     |     |     |
| n..3  hasta 3 caracteres numéricos                 |     |     |     |     |
| an..3  hasta 3 caracteres alfa-numéricos           |     |     |     |     |

Asímismo, la obligatoriedad o no de un determinado elemento se identifica por la siguiente
nomenclatura:

M : Mandatorio u obligatorio
C: Condicional u opcional

En relación a la identificación del formato de los elementos de  datos se especifica lo
siguiente:

| n(12,2)  | elemento  | numérico  | hasta12  | enteros+punto  |
| -------- | --------- | --------- | -------- | -------------- |
~ 3 ~
decimal+hastadosdecimales
| n(2,2)  | elemento  | numérico  | hasta  | 2  enteros+punto  |
| ------- | --------- | --------- | ------ | ----------------- |
decimal+hastadosdecimales
| F#####                    | elemento  | inicia  con  | la  letra  | F  seguida  |
| ------------------------- | --------- | ------------ | ---------- | ----------- |
| decincodígitosYYYY-MM-DD  |           | formato      | fecha      | yyyy=año,   |
mm=mes,dd=día

Nota de Débito Electrónica  ~ 3 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
 CONTENIDO DE LA NOTA DE DÉBITO ELECTRONICA

|                                                |           |       | Cardinalidad  | Valor/   |                |
| ---------------------------------------------- | --------- | ----- | ------------- | -------- | -------------- |
| Raíz  Nodo                                     | Atributo  | DATO  |               |          | Observaciones  |
|                                                |           |       | UBL           | Formato  |                |
| /DebitNote                                     |           |       | -             |          |                |
|                                                |           |       |               |          |                |
| /DebitNote/ext:UBLExtensions                   |           |       | 0..1          |          |                |
| /DebitNote/ext:UBLExtensions/ext:UBLExtension  |           |       | 1..n          |          |                |
/DebitNote/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent  1
|   ds:Signature      |     | Firma Digital    |       |        |     |
| ------------------- | --- | ---------------- | ----- | ------ | --- |
|   cbc:UBLVersionID  |     | Versión del UBL  | 0..1  | "2.1"  |     |
  cbc:CustomizationID    Versión de la estructura del documento  0..1  "2.0"

cbc:ID    Serie y número del comprobante  1  F###-NNNNNNNN
|   cbc:IssueDate  |     | Fecha de emisión  | 1     | yyyy-mm-dd   |     |
| ---------------- | --- | ----------------- | ----- | ------------ | --- |
|   cbc:IssueTime  |     | Hora de emisión   | 0..1  | hh-mm-ss.0z  |     |
|   cbc:Note       |     | Leyenda           | 0..n  | an..100      |     |
    @languageLocaleID  Código de leyenda  0..1  "urn:pe:gob:sunat:cpe:see:  Catálogo 52

gem:catalogos:catalogo52"
  cbc:DocumentCurrencyCode    Código de tipo de moneda en la cual se  0..1  an3  Catálogo 02

emite la nota de Débito electrónica
| /DebitNote/cac:DiscrepancyResponse  |     |     | 0..n  |     |     |
| ----------------------------------- | --- | --- | ----- | --- | --- |
  cbc:ReferenceID    Serie  y  número  de  comprobante  1  NNNN-NNNNNNNN\
|     |     | afectado  |     | F###-NNNNNNNN  |     |
| --- | --- | --------- | --- | -------------- | --- |

cbc:ResponseCode    Código de tipo de nota de Débito  0..1  n2  Catálogo 09

|   cbc:Description  |     | Motivo o sustento  | 0..n  | an..250  |     |
| ------------------ | --- | ------------------ | ----- | -------- | --- |
/DebitNote/cac:BillingReference/cac:DebitNoteDocumentReference  0..1
  cbc:ID    Serie y número del comprobante que  1  NNNN-NNNNNNNN/
|     |     | modifica  |     | F###-NNNNNNNN  |     |
| --- | --- | --------- | --- | -------------- | --- |
  cbc:DocumentTypeCode    Código  de  tipo  de  comprobante  que  0..1  n2  Catálogo 01

modifica

| Nota de Débito Electrónica  |     |     |     |     | ~ 4 ~  |
| --------------------------- | --- | --- | --- | --- | ------ |

Guía de elaboración de documentos electrónicos XML - UBL 2.1
| /DebitNote/cac:DespatchDocumentReference  |     |     | 0..n  |     |     |
| ----------------------------------------- | --- | --- | ----- | --- | --- |
  cbc:ID    Serie y número de la guía de remisión  1  NNNN-NNNNNNNN/
|     |     | (comprobante de referencia)  |     | G###-NNNNNNNN  |     |
| --- | --- | ---------------------------- | --- | -------------- | --- |
R###-NNNNNNNN
  cbc:DocumentTypeCode    Código  de  tipo  de  comprobante  0..1  n2  Catálogo 01
(comprobante de referencia)
| /DebitNote/cac:AdditionalDocumentReference  |     |     | 0..n  |     |     |
| ------------------------------------------- | --- | --- | ----- | --- | --- |
  cbc:ID    Serie  y  número  del  comprobante  de  1  an..30
referencia
  cbc:DocumentTypeCode    Código  de  tipo  de  comprobante  de  0..1  n2  Catálogo 12
referencia
/DebitNote/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme  0..n
  cbc:RegistrationName    Nombre o razón social del emisor  0..1  an..100
|   cbc:CompanyID  |     | Número de RUC del emisor  | 0..1  | n11  |     |
| ---------------- | --- | ------------------------- | ----- | ---- | --- |
    @schemeID  Tipo de Documento de Identidad del  0..1  an1  Catálogo 06
Emisor
|     | @schemeName  | -   | 0..1  | "SUNAT:Identificador de  |     |
| --- | ------------ | --- | ----- | ------------------------ | --- |
Documento de Identidad"
|     | @schemeAgencyName  | -   | 0..1  | "PE:SUNAT"                  |     |
| --- | ------------------ | --- | ----- | --------------------------- | --- |
|     | @schemeURI         | -   | 0..1  | "urn:pe:gob:sunat:cpe:see:  |     |
gem:catalogos:catalogo06"
/DebitNote/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress  0..1
  cbc:AddressTypeCode    Código del domicilio fiscal o de local  0..1  n4
anexo del emisor
/DebitNote/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme  0..n
  cbc:RegistrationName    Nombre o razón social del adquirente o  0..1  an..100
usuario
  cbc:CompanyID    Número  de  RUC  del  adquirente  o  0..1  n11
usuario
    @schemeID  Tipo de Documento de Identidad del  0..1  an1  Catálogo 06
Emisor
|     | @schemeName  | -   | 0..1  | "SUNAT:Identificador de  |     |
| --- | ------------ | --- | ----- | ------------------------ | --- |
Documento de Identidad"
|     | @schemeAgencyName  | -   | 0..1  | "PE:SUNAT"                  |     |
| --- | ------------------ | --- | ----- | --------------------------- | --- |
|     | @schemeURI         | -   | 0..1  | "urn:pe:gob:sunat:cpe:see:  |     |
gem:catalogos:catalogo06"

| Nota de Débito Electrónica  |     |     |     |     | ~ 5 ~  |
| --------------------------- | --- | --- | --- | --- | ------ |

Guía de elaboración de documentos electrónicos XML - UBL 2.1
| /DebitNote/cac:TaxTotal  |     |                          | 0..n  |          |     |
| ------------------------ | --- | ------------------------ | ----- | -------- | --- |
|   cbc:TaxAmount          |     | Monto total del tributo  | 1     | n(12,2)  |     |
    @currencyID  Código de tipo de moneda del monto  1  an3  Catálogo 02

total del tributo
| /DebitNote/cac:TaxTotal/cac:TaxSubtotal  |     |                          | 0..n  |          |     |
| ---------------------------------------- | --- | ------------------------ | ----- | -------- | --- |
|   cbc:TaxAmount                          |     | Monto total del tributo  | 1     | n(12,2)  |     |
    @currencyID  Código de tipo de moneda del monto  1  an3  Catálogo 02

total del tributo
/DebitNote/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme  1
|   cbc:ID  |     | Código de tributo  | 0..1  | an4  | Catálogo 05 |
| --------- | --- | ------------------ | ----- | ---- | ----------- |

|   cbc:Name  |     | Nombre de tributo  | 0..1  | an..6  | Catálogo 05 |
| ----------- | --- | ------------------ | ----- | ------ | ----------- |

  cbc:TaxTypeCode    Código internacional tributo  0..1  an3  Catálogo 05

| /DebitNote/cac:LegalMonetaryTotal  |     |     | 1   |     |     |
| ---------------------------------- | --- | --- | --- | --- | --- |
  cbc:AllowanceTotalAmount    Monto total de descuentos globales del  0..1  n(12,2)
comprobante
    @currencyID  Código de tipo de moneda del monto  1  an3  Catálogo 02

|     |     | total  de  descuentos  globales  del  |     |     |     |
| --- | --- | ------------------------------------- | --- | --- | --- |
comprobante
  cbc:ChargeTotalAmount    Monto  total  de  otros  cargos  del  0..1  n(12,2)
comprobante
    @currencyID  Código de tipo de moneda del monto  1  an3  Catálogo 02

total de otros cargos del comprobante
  cbc:PrepaidAmount    Monto  total  de  anticipos  del  0..1  n(15,2)
comprobante
    @currencyID  Código de tipo de moneda del monto  1  an3  Catálogo 02

total de anticipos del comprobante
  cbc:PayableAmount    Importe total de la venta, cesión en uso  1  n(12,2)
o del servicio prestado
    @currencyID  Código tipo de moneda del importe total  1  an3  Catálogo 02

de la venta, cesión en uso o del servicio
prestado

| Nota de Débito Electrónica  |     |     |     |     | ~ 6 ~  |
| --------------------------- | --- | --- | --- | --- | ------ |

Guía de elaboración de documentos electrónicos XML - UBL 2.1
/DebitNote/cac:DebitNoteLine 1..n
cbc:ID Número de orden del Ítem 1 n..3
cbc:DebitedQuantity Cantidad de unidades del ítem 0..1 n(12,10)
@unitCode Unidad de medida del ítem 0..1 an..3 Catálogo 03
@unitCodeListID - 0..1 UN/ECE rec 20
@unitCodeListAgencyName - 0..1 United Nations Economic
Commission for Europe
cbc:LineExtensionAmount Valor de venta del ítem 1 n(12,2)
@currencyID Código de tipo de moneda del valor de 1 an3 Catálogo 02
venta del ítem
/DebitNote/cac:DebitNoteLine/cac:PricingReference/cac:AlternativeConditionPrice 0..n
cbc:PriceAmount Precio de venta unitario/ Valor 1 n(12,10)
referencial unitario en operaciones no
onerosas
@currencyID Código de tipo de moneda del precio de 1 an3 Catálogo 02
venta unitario o valor referencial unitario
cbc:PriceTypeCode Código de tipo de precio 0..1 an2 Catálogo 16
/DebitNote/cac:DebitNoteLine/cac:TaxTotal 0..n
cbc:TaxAmount Monto de tributo del ítem 1 n(12,2)
@currencyID Código de tipo de moneda del monto de 1 an3 Catálogo 02
tributo del ítem
/DebitNote/cac:DebitNoteLine/cac:TaxTotal/cac:TaxSubtotal 0..n
cbc:TaxAmount Monto de tributo del ítem 1 n(12,2)
@currencyID Código de tipo de moneda del monto de 1 an3 Catálogo 02
tributo del ítem
/DebitNote/cac:DebitNoteLine/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory 1
cbc:TaxExemptionReasonCode Código de tipo de afectación del IGV 0..1 an2 Catálogo 07
cbc:TierRange Código de tipo de sistema de ISC 0..1 an2 Catálogo 08
/DebitNote/cac:DebitNoteLine/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme 1
cbc:ID Código de tributo 0..1 an4 Catálogo 05
cbc:Name Nombre de tributo 0..1 an..6 Catálogo 05
cbc:TaxTypeCode Código internacional tributo 0..1 an3 Catálogo 05
Nota de Débito Electrónica ~ 7 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
| /DebitNote/cac:DebitNoteLine/cac:Item  |     |     | 1   |     |     |
| -------------------------------------- | --- | --- | --- | --- | --- |
  cbc:Description    Descripción  detallada  del  servicio  0..n  an..250
prestado, bien vendido o cedido en uso,
indicando las características.
/DebitNote/cac:DebitNoteLine/cac:Item/cac:SellersItemIdentification  0..1
|   cbc:ID  |     | Código de producto del ítem  | 1   | an..30  |     |
| --------- | --- | ---------------------------- | --- | ------- | --- |
/DebitNote/cac:CreditLine/cac:Item/cac:CommodityClassification  0..1
  cbc:ItemClassificationCode    Código de producto (SUNAT)  1  n8
|     | @listID          |     | 0..1  | UNSPSC               |     |
| --- | ---------------- | --- | ----- | -------------------- | --- |
|     | @listAgencyName  |     | 0..1  | GS1 US               |     |
|     | @listName        |     | 0..1  | Item Classification  |     |
/DebitNote/cac:CreditLine/cac:Item/cac:AdditionalItemProperty  0..n
  cbc:Name    Nombre  del  concepto  o  elemento  a  1  an..100
consignar
  cbc:NameCode    Código  del  concepto  o  elemento  a  0..1  8000 ó 8001  Catálogo 55

consignar
|     | @listName  | -   | 0..1  | "SUNAT:Identificador de  |     |
| --- | ---------- | --- | ----- | ------------------------ | --- |
la propiedad del ítem"
|     | @listAgencyName  | -   | 0..1  | "PE:SUNAT"  |     |
| --- | ---------------- | --- | ----- | ----------- | --- |
  cbc:Value    Valor  del  concepto  o  elemento  a  0..1  an..100
consignar
/DebitNote/cac:CreditLine/cac:Item/cac:AdditionalItemProperty/cac:UsabilityPeriod  0..1
|   cbc:StartDate                         |     | Fecha de inicio          | 0..1  | yyyy-mm-dd  |     |
| --------------------------------------- | --- | ------------------------ | ----- | ----------- | --- |
| /DebitNote/cac:DebitNoteLine/cac:Price  |     |                          | 0..1  |             |     |
|   cbc:PriceAmount                       |     | Valor unitario del ítem  | 1     | n(12,10)    |     |
    @currencyID  Código de tipo de moneda del valor  1  an3  Catálogo 02
unitario del ítem

| Nota de Débito Electrónica  |     |     |     |     | ~ 8 ~  |
| --------------------------- | --- | --- | --- | --- | ------ |

Guía de elaboración de documentos electrónicos XML - UBL 2.1
1.2 Estructura de Nota de Débito vs FormatoXML
N REQUISITO
1° Firma Digital
<ext:UBLExtensions>
<ext:UBLExtension>
<ext:ExtensionContent>
<ds:Signature Id="signatureKG">
<ds:SignedInfo>
<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-
20010315#WithComments"/>
<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#dsa-sha1"/>
<ds:Reference URI="">
<ds:Transforms>
<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-
signature"/>
</ds:Transforms>
<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
<ds:DigestValue>+pruib33lOapq6GSw58GgQLR8VGIGqANloj4EqB1cb4=</ds:DigestValue>
</ds:Reference>
</ds:SignedInfo>
<ds:SignatureValue>Oatv5xMfFInuGqiX9SoLDTy2yuLf0tTlMFkWtkdw1z/Ss6kiDz+vIgZhgKfIaxp+JbVy57 GT52f1
8D6+WMYZ0xOxTK2mojNkJNewwTTXzqOqrrAlObs9YoS5JAQAMi/TwkR4brNniU9tVwyybirHxw0H
WVzN2bB43yQd9hOlXzRUYpC8/sXw78h7ME3E/zeu882aOFySOnHWB63imBQGcYBV+LIGR/JW8ER+
0VLMLatdwPVRbrWmz1/NIy5CWp1xWMaM6fC/9SXV0O1Lqopk0UeX2I2yuf05QhmVfjgUu6GnS3m6
o6zM9J36iDvMVZyj7vbJTwI8SfWjTSNqxXlqPQ==</ds:SignatureValue>
<ds:KeyInfo>
<ds:X509Data>
<ds:X509Certificate>MIIF9TCCBN2gAwIBAgIGAK0oRTg/MA0GCSqGSIb3DQEBCwUAMFkxCzAJBgNVB
AYTAlRSMUowSAYD
VQQDDEFNYWxpIE3DvGjDvHIgRWxla3Ryb25payBTZXJ0aWZpa2EgSGl6bWV0IFNhxJ9sYXnEsWPE
sXPEsSAtIFRlc3QgMTAeFw0wOTEwMjAxMTM3MTJaFw0xNDEwMTkxMTM3MTJaMIGgMRowGAYDVQQL
DBFHZW5lbCBNw7xkw7xybMO8azEUMBIGA1UEBRMLMTAwMDAwMDAwMDIxbDBqBgNVBAMMY0F5ZM
Sx bGFtYSBEYW7EscWfbWFubMSxayDFnmlya2V0bGVyIEd1cnVidTCCASIwDQYJKoZIhvcNAQEBBQAD
ggEPADCCAQoCggEBAKDt8WamB8ZCGqkLVP0rzY/BHGEXy8lT56m2dK7tswsvZxZYkV2qLGAxRlIY
ETAqBggrBgEFBQcCARYeaHR0cDovL2RlcG8ua2FtdXNtLmdvdi50ci9pbGtlMIHiBggrBgEFBQcC
AjCB1R6B0gBCAHUAIABzAGUAcgB0AGkAZgBpAGsAYQAgAGkAbABlACAAaQBsAGcAaQBsAGkAIABz
AGUAcgB0AGkAZgBpAGsAYQAgAHUAeQBnAHUAbABhAG0AYQAgAGUAcwBhAHMAbABhAHIBMQBuAT EA
IABvAGsAdQBtAGEAawAgAGkA5wBpAG4AIABiAGUAbABpAHIAdABpAGwAZQBuACAAdwBlAGIAIABz
AGkAdABlAHMAaQBuAGkAIAB6AGkAeQBhAHIAZQB0ACAAZQBkAGkAbgBpAHoALjAMBgNVHRMBAf8E
AjAAMBYGA1UdJQQPMA0GC2CGGAECAQEFBzIBMEEGA1UdHwQ6MDgwNqA0oDKGMGh0dHA6Ly9kZX
Bv LmthbXVzbS5nb3YudHIva3VydW1zYWwvbW1lc2hzLXQxLmNybDCBggYIKwYBBQUHAQEEdjB0MDwG
CCsGAQUFBzAChjBodHRwOi8vZGVwby5rYW11c20uZ292LnRyL2t1cnVtc2FsL21tZXNocy10MS5j
6Q3R1ZRSA49fYz6tDB4Ia5HVBXZODmrCs26XisHF6kuS5N/yGg8E7VC1BRr/SmxXeLTdjQYAfo7l
xCz4dT6wP5TOiBvF+lyWW1bi9nbliXyb/e5HjCp4k/ra9LTskjbY/Ukl5O8G9JEAViZkjvxDX7T0
yVRHgMGiioIKVMwU6Lrtln607BNurLwED0OeoZ4wBgkBiB5vXofreXrfN2pHZ24=
</ds:X509Certificate>
</ds:X509Data>
</ds:KeyInfo>
</ds:Signature>
</ext:ExtensionContent>
</ext:UBLExtension>
</ext:UBLExtensions>
<cac:Signature>
<cbc:ID>IDSignKG</cbc:ID>
<cac:SignatoryParty>
<cac:PartyIdentification>
<cbc:ID>20100113612</cbc:ID>
</cac:PartyIdentification>
<cac:PartyName>
<cbc:Name><![CDATA[K&G Laboratorios]]></cbc:Name>
</cac:PartyName>
</cac:SignatoryParty>
<cac:DigitalSignatureAttachment>
<cac:ExternalReference>
<cbc:URI>#signatureKG</cbc:URI>
</cac:ExternalReference>
</cac:DigitalSignatureAttachment>
</cac:Signature>
Nota de Débito Electrónica ~ 9 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
2 Versión del UBL
<cbc:UBLVersionID>2.1</cbc:UBLVersionID>
3 Versión de la estructura del documento
<cbc:CustomizationID>2.0</cbc:CustomizationID>
4 Numeración, conformada por serie y número correlativo
<cbc:ID>FD02-10</cbc:ID>
5 Fecha de emisión
<cbc:IssueDate>2017-06-28</cbc:IssueDate>
6 Leyenda
Código interno generado por el software de facturación
<cbc:Note languageLocaleID="3000">05010020170628000785</cbc:Note>
7 Tipo de moneda en la cual se emite la nota de Débito electrónica
<cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
8 Código del tipo de nota de Débito electrónica
<cac:DiscrepancyResponse>
<cbc:ReferenceID>F002-6</cbc:ReferenceID>
<cbc:ResponseCode>07</cbc:ResponseCode>
</cac:DiscrepancyResponse>
9 Motivo o Sustento
<cac:DiscrepancyResponse>
…
<cbc:Description><![CDATA[Devolución por ítem]]></cbc:Description>
</cac:DiscrepancyResponse>
10 Serie y número del documento que modifica
11 Tipo de documento del documento que modifica
<cac:BillingReference>
<cac:DebitNoteDocumentReference>
<cbc:ID>F002-6</cbc:ID>
<cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>
</cac:DebitNoteDocumentReference>
</cac:BillingReference>
12 Documento de referencia
13 Tipo y número de la guía de remisión relacionada con la operación
<cac:DespatchDocumentReference>
<cbc:ID>031-002020</cbc:ID>
<cbc:DocumentTypeCode>09</cbc:DocumentTypeCode>
</cac:DespatchDocumentReference>
14
Tipo y número de otro documento y
15
código relacionado con la operación
<cac:AdditionalDocumentReference>
<cbc:ID>10000120094</cbc:ID>
<cbc:DocumentTypeCode>05</cbc:DocumentTypeCode>
</cac:AdditionalDocumentReference>
16 Apellidos y nombres, denominación o razón social del Emisor
17 Número y Tipo de Documento del Emisor
18 Código del domicilio fiscal o de local anexo del emisor
<cac:AccountingSupplierParty>
<cac:Party>
<cac:PartyTaxScheme>
<cbc:RegistrationName><![CDATA[K&G Asociados S. A.]]></cbc:RegistrationName>
<CompanyID
schemeID="6"
schemeName="SUNAT:Identificador de Documento de Identidad"
schemeAgencyName="PE:SUNAT"
schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20100113612</CompanyID>
<cac:RegistrationAddress>
<cbc:AddressTypeCode>0001</cbc:AddressTypeCode>
</cac:RegistrationAddress>
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingSupplierParty>
Nota de Débito Electrónica ~ 10 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
19 Tipo y número de documento de identidad del adquirente o usuario
20 Número de RUC
21 Apellidos y nombres, denominación o razón social del adquirente o usuario
<cac:Party>
<cac:PartyTaxScheme>
<cbc:RegistrationName><![CDATA[CECI FARMA IMPORT S.R.L.]]></cbc:RegistrationName>
<cbc:CompanyID
schemeID="6"
schemeName="SUNAT:Identificador de Documento de Identidad"
schemeAgencyName="PE:SUNAT"
schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20102420706</cbc:CompanyID>
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingCustomerParty>
22 Monto total del impuestos
23 Monto las operaciones gravadas/exoneradas/inafectas del impuesto
24 Sumatoria de IGV
25 Sumatoria de ISC
26 Sumatoria de Otros Tributos
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">2124.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">11800.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">2124.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">31250.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">1250.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cac:TaxScheme>
<cbc:ID>9999</cbc:ID>
<cbc:Name>OTROS</cbc:Name>
<cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
27 Monto total de descuentos del comprobante
28 Importe total de la venta, cesión en uso o del servicio prestado
29
<cac:LegalMonetaryTotal>
<cbc:ChargeTotalAmount currencyID="PEN">600.00</cbc:ChargeTotalAmount>
<cbc:PrepaidAmount currencyID="PEN">100.00</cbc:PrepaidAmount>
<cbc:PayableAmount currencyID="PEN">500.00</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
30 Número de orden del Ítem
31 Unidad de medida por ítem Cantidad de unidades por ítem
32 Valor de venta del ítem
33
<cbc:ID>1</cbc:ID>
<cbc:DebitedQuantity
unitCode="CS"
unitCodeListID="UN/ECE rec 20"
unitCodeListAgencyName="United Nations Economic Commission for Europe">50</cbc:DebitedQuantity>
<cbc:LineExtensionAmount currencyID="PEN">1439.48</cbc:LineExtensionAmount>
Nota de Débito Electrónica ~ 11 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
34 Precio de venta unitario por item que modifica y código
<cac:PricingReference>
<cac:AlternativeConditionPrice>
<cbc:PriceAmount currencyID="PEN">34.99</cbc:PriceAmount>
<cbc:PriceTypeCode>01</cbc:PriceTypeCode>
</cac:AlternativeConditionPrice>
</cac:PricingReference>
35 Valor referencial unitario por ítem en operaciones no onerosas
<cac:PricingReference>
<cac:AlternativeConditionPrice>
<cbc:PriceAmount currencyID="PEN">250.00</cbc:PriceAmount>
<cbc:PriceTypeCode>02</cbc:PriceTypeCode>
</cac:AlternativeConditionPrice>
</cac:PricingReference>
36 Monto de tributo del ítem (IGV)
Monto de tributo del ítem (ISC)
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">259.11</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">1439.50</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">259.11</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">400.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">3333.33</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">400.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>
<cac:TaxScheme>
<cbc:ID>2000</cbc:ID>
<cbc:Name>ISC</cbc:Name>
<cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
37 Descripción detallada del servicio prestado, bien vendido o cedido en uso, indicando las características
<cbc:Description><![CDATA[Por aplicación de intereses compensatorios y moratorios según contrato N°
9685112]]></cbc:Description>
38 Código de producto
39 Código de producto SUNAT
<cbc: SellersItemIdentification>
<ID> Cap-258963</ID>
</cbc: SellersItemIdentification>
<cac:CommodityClassification>
<ItemClassificationCode
listID="UNSPSC"
listAgencyName="GS1 US"
listName="Item Classification">51121703</ ItemClassificationCode>
</cac:CommodityClassification>
40 Valor unitario del ítem
<cbc:PriceAmount CurrencyID="PEN" >785.20</cbc:PriceAmount>
Nota de Débito Electrónica ~ 12 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
1.3 Normas de Uso del Formato de la Nota de Débito Electrónica
A. Normas de Uso
Como se ha indicado, el formato UBL está basado en el uso de un documento XML para
presentar todos los datos en forma jerárquica. El formato especifica que para un archivo se
especifique toda la información de una y solo una nota de Débito. Como dicha representación se
basa en XML debe existir un único tag que engloba a todos los demás, dicha etiqueta es
DebitNote.
<DebitNote>
......
</DebitNote>
Para un mejor entendimiento de la estructura del archivo XML, se describe a continuación los
elementos que conforman la nota de Débito para el modelo Peruano, así como también los
elementos complejos más importantes.
A.1 Elementos de la Nota de Débito
A continuación se detallan los elementos que forman parte del documento Nota de Débito. En
cada uno de ellos se indica una explicación de la información que almacena, si es obligatorio
o no para que el documento sea correcto, su ubicación dentro del documento, un ejemplo y
una breve explicación de acuerdo al estándar UBL.
En la descripción UBL, para una mejor comprensión de los elementos de datos, se describen
solo aquellos tags que son necesarios para el uso tributario y que son requeridos por la
administración.
Nota de Débito Electrónica ~ 13 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
1. Firma Digital
Obligatorio.
Es el conjunto de datos asociados al documento electrónico que se firma y permite la
identificación del signatario (emisor de la factura electrónica) y ha sido creada por medios
que éste mantiene bajo su control, de manera que está vinculada únicamente al signatario
y a los datos a los que refiere.
La firma deberá realizarse con el certificado digital que el emisor de la nota de débito
comunicó previamente a SUNAT.
La firma se consignará en dos contenedores que corresponden a tipos complejos. Estos son
la firma digital de acuerdo a UBL y un componente de extensión.
Ubicación
//DebitNote/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature
//DebitNote/cac:Signature
Ejemplo
Un ejemplo de declaración de firma electrónica en el contenedor UBLExtensions sería:
<ext:UBLExtensions>
<ext:UBLExtension>
<ext:ExtensionContent>
<ds:SignatureId="signatureKG">
<ds:SignedInfo>
<ds:CanonicalizationMethodAlgorithm="http://www.w3.org/TR/2001/REC-xml-c14n-
20010315#WithComments"/>
<ds:SignatureMethodAlgorithm="http://www.w3.org/2000/09/xmldsig#dsa-sha1"/>
<ds:ReferenceURI="">
<ds:Transforms>
<ds:TransformAlgorithm="http://www.w3.org/2000/09/xmldsig#enveloped-
signature"/>
</ds:Transforms>
<ds:DigestMethodAlgorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
<ds:DigestValue>+pruib33lOapq6GSw58GgQLR8VGIGqANloj4EqB1cb4=</ds:DigestValue>
</ds:Reference>
</ds:SignedInfo>
<ds:SignatureValue>Oatv5xMfFInuGqiX9SoLDTy2yuLf0tTlMFkWtkdw1z/Ss6kiDz+vIgZhgKfIaxp+JbVy57GT5
8D6+WMYZ0xOxTK2mojNkJNewwTTXzqOqrrAlObs9YoS5JAQAMi/TwkR4brNniU9tVwyybirHxw0H
WVzN2bB43yQd9hOlXzRUYpC8/sXw78h7ME3E/zeu882aOFySOnHWB63imBQGcYBV+LIGR/JW8ER+
0VLMLatdwPVRbrWmz1/NIy5CWp1xWMaM6fC/9SXV0O1Lqopk0UeX2I2yuf05QhmVfjgUu6GnS3m6
o6zM9J36iDvMVZyj7vbJTwI8SfWjTSNqxXlqPQ==</ds:SignatureValue>
<ds:KeyInfo>
<ds:X509Data>
<ds:X509Certificate>MIIF9TCCBN2gAwIBAgIGAK0oRTg/MA0GCSqGSIb3DQEBCwUAMFkxCzAJBgNVBAY
TAlRSMUowSAYD
VQQDDEFNYWxpIE3DvGjDvHIgRWxla3Ryb25payBTZXJ0aWZpa2EgSGl6bWV0IFNhxJ9sYXnEsWPE
sXPEsSAtIFRlc3QgMTAeFw0wOTEwMjAxMTM3MTJaFw0xNDEwMTkxMTM3MTJaMIGgMRowGAYDVQQL
ETAqBggrBgEFBQcCARYeaHR0cDovL2RlcG8ua2FtdXNtLmdvdi50ci9pbGtlMIHiBggrBgEFBQcC
AjCB1R6B0gBCAHUAIABzAGUAcgB0AGkAZgBpAGsAYQAgAGkAbABlACAAaQBsAGcAaQBsAGkAIABz
AGUAcgB0AGkAZgBpAGsAYQAgAHUAeQBnAHUAbABhAG0AYQAgAGUAcwBhAHMAbABhAHIBMQBuATEA
DQYJKoZIhvcNAQELBQADggEBAGCcBJ7cEfYc2MaPchbc1yPXku8V8SOWpjg+jrTXBW98dy9HvciW
xCz4dT6wP5TOiBvF+lyWW1bi9nbliXyb/e5HjCp4k/ra9LTskjbY/Ukl5O8G9JEAViZkjvxDX7T0
yVRHgMGiioIKVMwU6Lrtln607BNurLwED0OeoZ4wBgkBiB5vXofreXrfN2pHZ24=
</ds:X509Certificate>
</ds:X509Data>
</ds:KeyInfo>
</ds:Signature>
</ext:ExtensionContent>
</ext:UBLExtension>
</ext:UBLExtensions>
Nota de Débito Electrónica ~ 14 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Un ejemplo de declaración de firma electrónica en el contenedor cac:Signature sería:
<cac:Signature>
<cbc:ID>IDSignKG</cbc:ID>
<cac:SignatoryParty>
<cac:PartyIdentification>
<cbc:ID>20100113612</cbc:ID>
</cac:PartyIdentification>
<cac:PartyName>
<cbc:Name><![CDATA[K&G Laboratorios]]></cbc:Name>
</cac:PartyName>
</cac:SignatoryParty>
<cac:DigitalSignatureAttachment>
<cac:ExternalReference>
<cbc:URI>#signatureKG</cbc:URI>
</cac:ExternalReference>
</cac:DigitalSignatureAttachment>
</cac:Signature>
Descripción UBL
UBLExtensions. Contenedor de Componentes de extensión. Se incorporan definiciones
estructuradas cuando sean de interés conjunto para emisores y receptores, y no estén ya
definidas en el esquema de la nota de débito. Se detalla más adelante (punto B.2.1).
Se utilizará el componente Extensions de UBL 2.1 para incorporar la firma electrónica
XMLDSIG1.
cac:Signature. Utilizado para identificar al firmante y otro tipo de información relacionada
con el mismo. Su uso se da principalmente para especificar la ubicación de la firma
electrónica ya sea que este embebida (dentro del mansaje) o desacoplada.
 cbc:ID. Obligatorio. Identificador de lafirma

 cac:SignatoryParty. Obligatorio. Asociación con la parte firmante, la cual para
nuestro caso deberá estar relacionado con el emisor de la nota dedébito.
 PartyIdentification. Obligatorio. A través del elemento ID, se consigna el RUC de la
partefirmante.
 PartyName. Obligatorio. A través del elemento Name, se consigna el nombre o
razón social de la partefirmante.
1
Es un estándar creado por la W3C que recoge las reglas básicas de creación y procesamiento de firmas electrónicas
de documentos, principalmente en XML. Las firmas [XMLDSig] son firmas digitales creadas y pensadas para
transacciones XML. Dentro de la firma electrónica en formato XML, existen diferentes “subtipos de formatos”, dentro
de los cuales destacan por encima de todos el XML Dsig y la variante de este, el XML Advanced Electronic Signatures
(XAdES).)
Nota de Débito Electrónica ~ 15 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
 cac:DigitalSignatureAttachment. Obligatorio. En este componente se puede
referenciar la firma del documento como una ExternalReference a una URI local o
remota.
o ExternalReference. Obligatorio. Información acerca de un documento
vinculado. Los vínculos pueden ser externos (referenciados mediante un
elemento URI), internos (accesibles mediante un elemento MIME) o
pueden estar contenidos dentro del mismo documento en el que se alude
a ellos (mediante elementos Documento Incrustado). Este último será el
caso a utilizar, es decir una referencia dentro del mismo documento
DebitNote, específicamente en el componente UBLExtensions.
2. Versión del UBL
Obligatorio. Versión del esquema UBL que define todos los elementos que se podrían
encontrar en este documento. Para el caso peruano se ha utilizado la versión “2.1”.
Ubicación
//DebitNote/cbc:UBLVersionID
Ejemplo
<cbc:UBLVersionID>2.1</cbc:UBLVersionID>
Descripción UBL cbc:UBLVersionID
Versión UBL usada para esquematizar y definir los elementos contenidos en el documento.
3. Versión de la estructura del documento
Obligatorio. Identifica una personalización de UBL definida para un uso específico. Para
nuestro caso corresponderá a la versión 2.0 de la nota de débito electrónica. Por cada
variación o adecuación del esquema se deberá de aumentar la versión, la cual contemplará las
nuevas validaciones para los elementos de datos establecidos.
Ubicación
//DebitNote/cbc:CustomizationID
Ejemplo
<cbc:CustomizationID>2.0</cbc:CustomizationID>
Descripción UBL
cbc:CustomizationID
Elemento usado para identificar la personalización, definida por el usuario de UBL, sobre los
documentos asociados.
Nota de Débito Electrónica ~ 16 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
4. Numeración, conformada por serie y número correlativo
Obligatorio. Identificador de documento, para el caso peruano este elemento contendrá serie
de la nota de débito y el número correlativo de la misma.
La serie será alfanumérica compuesta por cuatro caracteres:
‒ Si la nota modifica una factura, el primer carácter de la izquierda debe iniciar en” F”, los
otros tres caracteres pueden ser alfanuméricos. El número correlativo podrá ser hasta de
ocho (8) caracteres iniciando en1.
‒ Si la nota modifica una boleta de venta, el primer carácter de la izquierda debe iniciar en B,
los otros tres caracteres pueden ser alfanuméricos. El número correlativo podrá ser hasta
de ocho (8) caracteres iniciando en1.
Esta numeración será independiente del número correlativo de las notas de débito emitidas en
formato impreso y/o importado por imprenta autorizada.
La serie de la nota de débito, salvo el primer carácter, no necesariamente debe coincidir con la
la serie del comprobante de pago que es materia deajuste.
Ubicación
//DebitNote/cbc:ID
<cbc:ID>FD01-10</cbc:ID>
Ejemplo
Descripción UBL
cbc:ID
Identificador único de la nota de débito asignada por el emisor.
5. Fecha de emisión
Obligatorio. Corresponde a la fecha que, de conformidad con las normas legales, debe emitirse
el documento por aquellas circunstancias que impliquen el aumento del valor de las operaciones,
sustentadas en un comprobante de pago previamente emitido.
Ubicación
//DebitNote/cbc:IssueDate
Ejemplo
<cbc:IssueDate>2011-06-28</cbc:IssueDate>
Descripción UBL
cbc:IssueDate. Fecha de emisión del documento. El tipo DateType se corresponde con el tipo
Date de XML por lo que el formato deberá ser yyyy-mm-dd.
Nota de Débito Electrónica ~ 17 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
6. Leyendas
Elemento utilizado para consignar la siguiente leyenda:
 Código interno generado por el software de Facturación.
Se consignará la llave única o clave única o clave primaria del software donde se generó el
ingreso de información para la generación del comprobante de pago electrónico.
Tratándode de software contables intregados (ERP) se podrá consignar el código contable del
asiento del libro diario que generó la transacción.
En el atributo @languageLocaleID se debe consignar el código “3000” (según Catálogo No.
52).
Ubicación
//DebitNote/cbc:Note@languageLocaleID
Ejemplo
<DebitNote>
…
<cbc:Note
languageLocaleID="3000">05010020170428000005</cbc:Note>
…
</DebitNote>
Descripción UBL
cbc:Note
Para hacer uso de este elemento, es necesario consignar el atributo que identifique la leyenda
que se está utilizando (languageLocaleID) y el texto de la leyenda o valor según fuera el caso
(cbc:Note).
7. Tipo de moneda en la cual se emite la nota de débito electrónica
Obligatorio. Código de moneda empleada genéricamente en el documento y que debe ser
igual al tipo de moneda de el(los) comprobante(s) de pago que se modifica(n). Los códigos se
especifican en un archivo de tipo CodeList incluido en los esquemas UBL y que corresponde a
la norma ISO 4217 – Currency.
Ubicación
//DebitNote/cbc:DocumentCurrencyCode
Ejemplo
<cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
Descripción UBL
cbc:DocumentCurrencyCode
Moneda en la que el documento se presenta. Tener en cuenta que el código de moneda
también debe colocarse como atributo en todos aquellos campos que almacenan un monto de
tipo monetario.
Nota de Débito Electrónica ~ 18 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
8. Código del tipo de Nota de débito electrónica
Obligatorio.Motivo por el cual se emite la nota de débito. Para el caso peruano el elemento
cbc:ResponseCode podrá adoptar los siguientes valores:
01 Interes por mora
02 Aumento en el valor
Ubicación
//DebitNote/cac:DiscrepancyResponse/cbc:ReferenceID
//DebitNote/cac:DiscrepancyResponse/cbc:ResponseCode
Ejemplo
<cac:DiscrepancyResponse>
<cbc:ReferenceID>F002-6</cbc:ReferenceID>
<cbc:ResponseCode>01</cbc:ResponseCode>
<cbc:Description><![CDATA[Interes por pago extemporáneo de Octubre 2016]]></cbc:Description>
</cac:DiscrepancyResponse>
Descripción UBL
cac:DiscrepancyResponse
Contiene el detalle del motivo de la emisión del documento en su conjunto. El uso de este tag
implica que obligatoriamente se consigne un campo cbc:ReferenceID, el cual para el caso
Peruano contendrá la identificación del documento modificado, a pesar de que este dato se
consigna también en otro elemento (cac:BillingReference).
Los elementos a utilizarse son los siguientes:
 cbc:ReferenceID: Obligatorio. Identifica al documento modificado por la nota de débito.
Se consignará en el formato: Serie-Número correlativo dedocumento.
 cbc:ResponseCode: Obligatorio. Código que representa el tipo de nota de débito
utilizada. Se utilizará el Catálogo No.09.
 cbc:Description: Obligatorio. Sustento o descripción del motivo de la nota de débito.
Sólo se puede consignar unadescripción.
Nota de Débito Electrónica ~ 19 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
9. Motivo o Sustento
Obligatorio. Elemento usado para describir el motivo o sustento de la emisión de la nota de
débito, explicación que debe ser acorde con el tipo de nota emitida.
Ubicación
//DebitNote/cac:DiscrepancyResponse/cbc:Description
Ejemplo
Ver numeral anterior.
Descripción UBL
Ver numeral anterior.
10. Serie y número del documento que modifica
Obligatorio. Asocia la nota de débito al comprobante de pago modificado.
Ubicación
//DebitNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID
Ejemplo
<cac:BillingReference>
<cac:InvoiceDocumentReference>
<cbc:ID>F001-2</cbc:ID>
<cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>
</cac:InvoiceDocumentReference>
</cac:BillingReference>
Descripción UBL
cac:BillingReference
Obligatorio: Tipo complejo, que contiene los siguientes elementos:
 cac:InvoiceDocumentReference. Obligatorio. Identificación del documento afectado por la
nota dedébito:
De los elementos que componen este tipo complejo y que serán utilizados en el documento
de tipo nota de débito tenemos:
o cbc:ID: Obligatorio. Identificación del número del documentomodificado.
o cbc:DocumentTypeCode: Opcional. Identificación del código del tipo de documento. Por
defecto el valor corresponderáa:
Nota de Débito Electrónica ~ 20 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
o 01-FacturaComercial
o 03-Boleta deVenta
o 12-Ticket de máquinaregistradora
11. Tipo de documento del documento quemodifica
Obligatorio. Se consigna el tipo de comprobante de pago. Se utilizará el Catálogo No. 01 del
anexo N° 8: “Código de Tipo de documento”.
Ubicación
//DebitNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode
Ejemplo
Ver numeral anterior.
Descripción UBL
Ver numeral anterior.
12. Documento dereferencia
Opcional. Referencia a las guías de remisión remitente o transportista, según corresponda,
autorizado por la SUNAT para sustentar el traslado de los bienes. Pueden existir múltiples
guías de remisión, por lo que el número de elementos de este tipo es ilimitado. Se utilizará el
Catálogo N° 01: “Código de Tipo deDocumento”.
También referencia a cualquier otro documento, distintos a los señalados en el párrafo anterior,
asociado a la nota de débito. Para estos casos se utilizará el Catálogo No. 12: “Códigos -
Documentos Relacionados Tributarios”.
Ubicación
a) En el caso de Guías deRemisión:
//DebitNote/cac:DespatchDocumentReference/cbc:ID
//DebitNote/cac:DespatchDocumentReference/cbc:DocumentTypeCode
b) En el caso de Otros documentosrelacionados:
//DebitNote/cac:AdditionalDocumentReference/cbc:ID
//DebitNote/cac:AdditionalDocumentReference/cbc:DocumentTypeCode
Ejemplo
<cac:DespatchDocumentReference>
<cbc:ID>0001-002020</cbc:ID>
<cbc:DocumentTypeCode>09</cbc:DocumentTypeCode>
</cac:DespatchDocumentReference>
<cac:AdditionalDocumentReference>
<cbc:ID>0081-024099</cbc:ID>
<cbc:DocumentTypeCode>12</cbc:DocumentTypeCode>
</cac:AdditionalDocumentReference>
Nota de Débito Electrónica ~ 21 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Descripción UBL
a) cac:DespatchDocumentReference
Tag que hace referencia a documentos de transporte asociados a la nota de débito.
De los elementos que componen este tipo complejo y que serán utilizados en el documento de
tipo nota de débito tenemos:
cbc:ID: Obligatorio. Identificación del número de guía autorizado por SUNAT. Estará
conformado por la serie y el número de documento, separado por un guión.
cbc:DocumentTypeCode: Obligatorio. Corresponde al código del tipo de documento al
que se hace referencia. Se utilizará de acuerdo al catálogo de códigos establecidos para
documentos (Catálogo No. 01).
b) cac:AdditionalDocumentReference
Tag que hace referencia a documentos asociados a la nota de débito.
De los elementos que componen este tipo complejo y que serán utilizados en el documento de
tipo nota de débito tenemos:
cbc:ID: Obligatorio. Identificación del número de documento asociado a la nota de débito.
cbc:DocumentTypeCode: Obligatorio. Corresponde al código del tipo de documento al que
se hace referencia. Se utilizará de acuerdo al catálogo de códigos establecidos para
documentos (Catálogo No. 12).
13. Apellidos y nombres o denominación o razón social
Obligatorio. Corresponde a los apellidos y nombres o denominación o razón social del emisor
de la nota de débito electrónica. Este debe ser acorde a lo registrado en el Registro Único de
Contribuyentes - RUC. Este requisito se encuentra contenido en el elemento complejo cac:Party
ubicado en el componente cac:AccountingSupplierParty.
Ubicación
//DebitNote/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:RegistrationName
Nota de Débito Electrónica ~ 22 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ejemplo
<cac:AccountingSupplierParty>
<cac:Party>
<cac:PartyTaxScheme>
…
<cbc:RegistrationName><![CDATA[K&G Asociados S. A.]]></cbc:RegistrationName>
…
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingSupplierParty>
Descripción UBL
cbc:RegistrationName
Se usa para indicar el nombre o razón social del contribuyente de acuerdo a la información
proporcianada al momento de su inscripción o modificación hacia la SUNAT.
14. Tipo y Número de RUC del Emisor.
Obligatorio. El tipo de documento del emisor siempre es 6, que corresponde al RUC. Además
de esto se debe consignar el número de RUC del emisor de la factura electrónicael cual debe
ser válido.
Ubicación
//DebitNote/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID @schemeID
@schemeName @schemeAgencyName @schemeURI
Ejemplo
<cac:AccountingSupplierParty>
<cac:Party>
<cac:PartyTaxScheme>
…
<cbc:CompanyID schemeID="6" schemeName="SUNAT:Identificador de Documento de Identidad"
schemeAgencyName="PE:SUNAT"
schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20100113612</cbc:CompanyID>
…
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingSupplierParty>
Descripción UBL
cac:AccountingSupplierParty
Estructura de datos del emisor. Tipo complejo que a su vez contiene un elemento Party que se
especificará más adelante.
 cbc:RegistrationName. Obligatorio. Nombre o denominación o razón social del emisor
del comprobante electrónico.
 cbc:CompanyID. Obligatorio. Identificación del emisor de la factura, deberá de indicarse
el Número de RUC del Emisor.
Nota de Débito Electrónica ~ 23 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Atributos
schemeName "SUNAT:Identificador de Documento de Identidad"
schemeAgencyName "PE:SUNAT"
schemeURI "urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
Valor de Códigos Catálogo N° 06
cbc: CompanyID
Código Concepto
6 REG. UNICO DE CONTRIBUYENTES
 cac:Party. Tener en cuenta el punto anterior.
15. Código del domicilio fiscal o de local anexo del emisor.
Corresponde informar el código del establecimiento donde se esta realizando la venta de los
bienes.
Ubicación
//DebitNote/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cb
c:AddressTypeCode
Ejemplo
<cac:AccountingSupplierParty>
…
<cac:Party>
…
<cac:PartyTaxScheme>
…
<cac:RegistrationAddress>
<cbc:AddressTypeCode>0011</cbc:AddressTypeCode>
</cac:RegistrationAddress>
<cac:TaxScheme>
<cbc:ID>-</cbc:ID>
</cac:TaxScheme>
…
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingSupplierParty>
Descripción UBL
cac:AddressTypeCode. Código de cuatro dígitos asignado por SUNAT, que identifica al
establecimiento anexo. Dicho código se genera al momento la respectiva comunicación del
establecimiento. Tratándose del domicilio fiscal y en el caso de no poder determinar el lugar de la
venta, informar “0000”.
Nota de Débito Electrónica ~ 24 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
16. Apellidos y nombres o denominación o razón social del adquirente o usuario.
Obligatorio. Corresponde a los apellidos y nombres o denominación o razón social del
adquirente o usuario.
Ubicación
/DebitNote/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:RegistrationName
Ejemplo
<cac:AccountingCustomerParty>
…
<cac:Party>
<cac:PartyTaxScheme>
…
<cbc:RegistrationName><![CDATA[CECI FARMA IMPORT S.R.L.]]></cbc:RegistrationName>
</cac:PartyTaxScheme>
…
</cac:Party>
</cac:AccountingCustomerParty>
Descripción UBL
cbc:RegistrationName
Se usará para indicar el nombre o razón social, según fuera el caso del cliente.
17. Tipo y número de documento de identidad del adquirente o usuario.
Obligatorio. El tipo de documento será de acuerdo al Catálogo N° 06 del anexo N° 8: “Códigos
de Tipos de Documentos de Identidad”.
Ubicación
/DebitNote/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID
@schemeID @schemeName @schemeAgencyName @schemeURI
Ejemplo
<cac:AccountingCustomerParty>
<cac:Party>
<cac:PartyTaxScheme>
<cbc:RegistrationName><![CDATA[CECI FARMA IMPORT S.R.L.]]></cbc:RegistrationName>
<cbc:CompanyID schemeID="6" schemeName="SUNAT:Identificador de Documento de
Identidad" schemeAgencyName="PE:SUNAT" schemeURI="
urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20100113612</cbc:CompanyID>
<cac:TaxScheme>
<cbc:ID>-</cbc:ID>
</cac:TaxScheme>
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingCustomerParty>
Descripción UBL
cac: AccountingCustomerParty
Estructura de datos del cliente.
 cbc:CompanyID. Obligatorio. Identificación del cliente, deberá de indicarse el documento
de identidad.
Atributos
Nota de Débito Electrónica ~ 25 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
schemeName "SUNAT:Identificador de Documento de Identidad"
schemeAgencyName "PE:SUNAT"
schemeURI "urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
Valor de Códigos Catálogo N° 06
cbc: CompanyID
Código Concepto
0 DOC.TRIB.NO.DOM.SIN.RUC
1 DOC. NACIONAL DE IDENTIDAD
4 CARNET DE EXTRANJERIA
6 REG. UNICO DE CONTRIBUYENTES
7 PASAPORTE
A CED. DIPLOMATICA DE IDENTIDAD
B DOC.IDENT.PAIS.RESIDENCIA-NO.D
C Tax Identification Number - TIN – Doc Trib PP.NN
D Identification Number - IN – Doc Trib PP. JJ
 cac:Party. Tener en cuenta el punto anterior en relación a este elemento.
18. Monto Total de Impuestos.
Corresponde al importe total de impuestos ISC, IGV e IVAP de Corresponder.
Ubicación
//DebitNote/cac:TaxTotal/cbc:TaxAmount
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">59210.65</cbc:TaxAmount>
…
</cac:TaxTotal>
Ejemplo
Descripción UBL
cbc:TaxAmount
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Se deberá colocar la
sumatoria total de los impuestos.
19. Total valor de venta - operaciones gravadas.
Este elemento es usado solo si al menos una línea de ítem está gravada con el IGV. Contiene a
la sumatoria de los valores de venta gravados por ítem (ver definición de valor de venta en
punto 26). El total valor de venta no incluye IGV, ISC, cargos y otros Tributos si los hubiera.
La sumatoria tampoco debe contener el valor de venta de las transferencias de bienes o
servicios prestados a título gratuito comprendidos en la factura y que estuviesen gravados con
el IGV.
Ubicación
/DebitNote/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount @currencyID
Nota de Débito Electrónica ~ 26 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ejemplo
<cac:TaxTotal>
…
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">8560.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">1540.80</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name> IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
…
</cac:TaxTotal>
Descripción UBL
cac:TaxSubTotal
Para hacer uso de este elemento, es necesario consignar el monto base sobre el cual se está
aplicando el impuesto informado, esto se consigna en el elemento cbc:TaxableAmount. En el
elemento cbc:TaxAmount se coloco el total de impuestos de ser el caso.
cac:TaxCategory
Así mismo, se hace necesario especificar la categoría del impuesto por el cual se está
reportando esto se realiza con el elemento cbc:ID y los atributos:
Valor de Códigos cbc:ID Catálogo N° 05
Código Descripción
S IGV
cac:TaxScheme
Por otro lado, es importante indicar la clase de impuesto que se está informando para ello con el
elemento cbc:ID reportaremos de acuerdo a la información del Catálogo N° 5, que para el caso
de IGV es el código 1000 y a los siguientes atributos:
cbc:Name
Este elemento se utiliza para expresar en letras que la información que se está reportando se
encuentra: IGV (Se sigue el formato del Catálogo N° 5).
cbc:TaxTypeCode
Este elemento se utiliza para expresar a través de un código que la información que se está
reportando se encuentra inafecta, el valor de acuerdo Catálogo N° 5 es: VAT.
20. Total valor de venta - operaciones inafectas.
Este elemento es usado solo si al menos una línea de ítem se encuentra inafecta al IGV.
Contiene a la sumatoria de valor de venta por item inafectos (ver definición de valor de venta
x ítem en punto 26).
El valor de venta no incluye ISC, cargos u otros tributos si los hubiera. La sumatoria tampoco
Nota de Débito Electrónica ~ 27 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
debe contener el valor de venta de las transferencias de bienes o servicios prestados a título
gratuito comprendidos en la factura y que estuviesen inafectos al IGV.
Ubicación
/DebitNote/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount @currencyID
Ejemplo
<cac:TaxTotal>
…
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">320.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>O</cbc:ID>
<cac:TaxScheme>
<cbc:ID>9998</cbc:ID>
<cbc:Name> INAFECTO</cbc:Name>
<cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
…
</cac:TaxTotal>
Descripción UBL
cac:TaxSubTotal
Para hacer uso de este elemento, es necesario consignar el elemento cbc:TaxableAmount que
representa el importe que se encuentra inafecto del IGV.
Además de ello, se remitirá junto al atributo @currencyID con el valor “PEN”. En el elemento
cbc:TaxAmount se consigna 0.00
cac:TaxCategory
Así mismo, se hace necesario especificar la categoría del impuesto por el cual se está
reportando esto se realiza con el elemento cbc:ID y los atributos:
Valor de Códigos cbc:ID Catálogo N° 05
Código Descripción
O Inafecto
cac:TaxScheme
Por otro lado, es importante indicar la clase de impuesto que se está informando para ello con el
elemento cbc:ID reportaremos de acuerdo a la información del Catálogo N° 5, que para el caso
de operaciones inafectas es el código 9998 y a los siguientes atributos:
cbc:Name
Este elemento se utiliza para expresar en letras que la información que se está reportando se
encuentra: INAFECTO (Se sigue el formato del Catálogo N° 5).
cbc:TaxTypeCode
Este elemento se utiliza para expresar a través de un código que la información que se está
reportando se encuentra inafecta, el valor de acuerdo Catálogo N° 5 es: FRE.
Nota de Débito Electrónica ~ 28 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
21. Total valor de venta - operaciones exoneradas.
Este elemento es usado solo si al menos una línea de ítem se encuentra exonerada al IGV.
Contiene a la sumatoria de valor de venta por ítem exonerados por item (ver definición de valor
de venta x ítem en punto 26).
El valor de venta no incluye ISC, cargos u otros Tributos si los hubiera. La sumatoria tampoco
debe contener el valor de venta de las transferencias de bienes o servicios prestados a título
gratuito comprendidos en la factura y que estuviesen exonerados del IGV.
Ubicación
/DebitNote/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount @currencyID
<cac:TaxTotal>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">8560.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>E</cbc:ID>
<cac:TaxScheme>
<cbc:ID>9997</cbc:ID>
<cbc:Name> EXONERADO</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Ejemplo
Descripción UBL
cac:TaxSubTotal
Para hacer uso de este elemento, es necesario consignar el monto que se está informando
(cbc:TaxableAmount) con su respectivo atributo de tipo de moneda que le corresponda
(@currencyID). En el elemento cbc:TaxAmount se consigna 0.00.
cac:TaxCategory
Así mismo, se hace necesario especificar la categoría del impuesto por el cual se está
reportando esto se realiza con el elemento cbc:ID y los atributos:
Valor de Códigos cbc:ID Catálogo N° 05
Código Descripción
E Exonerado
cac:TaxScheme
Por otro lado, es importante indicar la clase de impuesto que se está informando para ello con el
elemento cbc:ID reportaremos de acuerdo a la información del Catálogo N° 5, que para el caso
de operaciones exoneradas es el código 9997 y a los siguientes atributos:
cbc:Name
Este elemento se utiliza para expresar en letras que la información que se está reportando se
encuentra: EXONERADO (Se sigue el formato del Catálogo N° 5).
Nota de Débito Electrónica ~ 29 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
cbc:TaxTypeCode
Este elemento se utiliza para expresar a través de un código que la información que se está
reportando es exonerada, el valor de acuerdo Catálogo N° 5 es: FRE.
22. Sumatoria de IGV.
Corresponde a la sumatoria del IGV del ajuste realizado con la nota de credito
El IGV = 18% de la suma: [Total valor de venta operaciones gravadas] + [Sumatoria ISC].
Para el caso peruano los elementos de identificación del tributo contenidos en:
.../cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/… Adoptarán los valores “1000”, “IGV” y
“VAT” respectivamente. (Catálogo No. 05)
Ubicación
//DebitNote/cac:TaxTotal/cbc:TaxAmount
Ejemplo
<cac:TaxTotal>
…
<cbc:TaxAmount currencyID="PEN">59210.65</cbc:TaxAmount>
…
<cac:TaxTotal>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">328948.05</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">59210.65</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:ID>S</cbc:ID>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Descripción UBL
cbc:TaxAmount
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Para hacer uso de este
elemento, es necesario además colocar datos que permita identificar el tributo que se está
informando.
Además, se debe tomar en cuenta que el campo cbc:TaxAmount se consigna a nivel del
cac:TaxTotal y a nivel del cac:TaxSubtotal. En ambos casos se consignará el mismo valor
correspondiente al monto del tributo.
23. Sumatoria de ISC.
Corresponde a la sumatoria del ISC del ajuste realizado con la nota de credito
Para el caso peruano los elementos de identificación del tributo contenidos en:
.../cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/… Adoptarán los valores
“2000”(catálogo No 05), “ISC” y “EXC” (catálogo No 05) respectivamente.
Nota de Débito Electrónica ~ 30 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ubicación
//DebitNote/cac:TaxTotal/cbc:TaxAmount
Ejemplo
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">8745.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">43725.00</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">8745.00</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID>2000</cbc:ID>
<cbc:Name>ISC</cbc:Name>
<cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Descripción UBL
cbc:TaxAmount
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Para hacer uso de este
elemento, es necesario además colocar datos que permita identificar el tributo que se está
informando.
Además, se debe tomar en cuenta que el campo cbc:TaxAmount se consigna a nivel del
cac:TaxTotal y a nivel del cac:TaxSubtotal. En ambos casos se consignará el mismo valor
correspondiente al monto del tributo.
24. Sumatoria de Otros Tributos.
Sumatoria de otros tributos, distintos al IGV o ISC, correspondientes al ajuste realizado con la
nota de débito, y que conforme a la regulación correspondiente deban estar desagregados en
la nota dedébito.
Para el caso peruano los elementos de identificación de este concepto contenidos en:
.../cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/… Adoptarán los valores “9999”
“OTROS” y “OTH” respectivamente. (Catálogo No. 05)
Ubicación
//DebitNote/cac:TaxTotal/cbc:TaxAmount
Ejemplo
<cac:TaxTotal>
<cbc:TaxAmountcurrencyID="PEN">39000.0</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmountcurrencyID="PEN">97500.00</cbc:TaxableAmount>
<cbc:TaxAmountcurrencyID="PEN">39000.0</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID>9999</cbc:ID>
<cbc:Name>OTROS</cbc:Name>
<cbc:TaxTypeCode>OTH</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Nota de Débito Electrónica ~ 31 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Descripción UBL
cbc:TaxAmount
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Para hacer uso de este
elemento, es necesario además colocar datos que permita identificar el tributo que se está
informando.
Además, se debe tomar en cuenta que el campo cbc:TaxAmount se consigna a nivel del
cac:TaxTotal y a nivel del cac:TaxSubtotal. En ambos casos se consignará el mismo valor
correspondiente al monto del tributo.
25. Total de Descuentos.
A través de este elemento se debe indicar el valor total de los descuentos realizados de ser el
caso.
Su propósito es permitir consignar en el comprobante de pago:
 la sumatoria de los descuentos de cada línea (descuentos por ítem), o
 la sumatoria de los descuentos de línea (ítem)
Ubicación
//DebitNote/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount
Ejemplo
<cac:LegalMonetaryTotal>
<cbc:AllowanceTotalAmount currencyID="PEN">9420.50</cbc:AllowanceTotalAmount>
</cac:LegalMonetaryTotal>
Descripción UBL
cbc:AllowanceTotalAmount
Para hacer uso de este elemento, es necesario consignar el valor del monto con su respectivo
atributo de tipo de moneda (@ currencyID). Revisar punto 7.
26. Importe total de la venta, de la cesión en uso o del servicio prestado.
Total valor de venta - operaciones gravadas (19) +
Total valor de venta - operaciones inafectas (20) +
Total valor de venta - operaciones exoneradas (21) +
Sumatoria IGV (22) +
Sumatoria ISC (23) +
Sumatoria otros tributos (24)
Ubicación
//DebitNote/cac:LegalMonetaryTotal/cbc:PayableAmount
Nota de Débito Electrónica ~ 32 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ejemplo
<cac:LegalMonetaryTotal>
…
<cbc:PayableAmountcurrencyID="PEN">45.34</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
Descripción UBL
cbc:PayableAmount
El campo cbc:PayableAmount se consigna dentro del elemento complejo
cac:LegalMonetaryTotal.
27. Número de orden del Ítem.
Número de la línea que es secuencial y se encuentra en cada línea que contiene la nota de
débito.
Ubicación
//DebitNote/cac:DebitNoteLine/cbc:ID
Ejemplo
<cac:DebitNoteLine>
<cbc:ID>1</cbc:ID>
….
</cac:DebitNoteLine>
Descripción UBL
cac:DebitNoteLine
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine, se detalle en
forma numérica en el orden que corresponde al ítem a informar.
28. Cantidad de unidades por ítem.
Se consignará la cantidad de bienes devueltos. En el caso de servicios se deberá consignar el
número “1”.
Ubicación
/DebitNote/cac:DebitNoteLine/cbc:DebitedQuantity @unitCode @unitCodeListID
@unitCodeListAgencyName
Ejemplo
<cbc:DebitedQuantity unitCode="CS" unitCodeListID="UN/ECE rec 20"
unitCodeListAgencyName="United Nations Economic Commission for
Europe">50</cbc:DebitedQuantity>
Descripción UBL
cbc:DebitedQuantity
Este campo se encuentra ubicado en el elemento complejo cac:DebitNoteLine, aquí se detalla
la cantidad de unidades de acuerdo a la unidad de medida que se esté informando.
Nota de Débito Electrónica ~ 33 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Atributos
unitCode Catálogo N° 3
unitCodeListID "UN/ECE rec 20"
unitCodeListAgencyName "United Nations Economic Commission for Europe"
Valor de Códigos cbc:ID Catálogo N° 03*
Código Descripción
NIU UNIDAD (BIENES)
ZZ UNIDAD (SERVICIOS)
*El resto de códigos se pueden verificar en el anexo II del siguiente link: Clic Aquí.
29. Valor de venta por ítem.
Este elemento es el producto de la cantidad por el valor unitario (Q x Valor Unitario) y la deducción
de los descuentos aplicados a dicho ítem (de existir). Este importe no incluye los tributos (IGV, ISC
y otros Tributos).
Nota: ver definición de valor unitario en punto 31.
Ubicación
//DebitNote/cac:DebitNoteLine/cbc:LineExtensionAmount @currencyID
Ejemplo
<cbc:LineExtensionAmount currencyID="PEN">172890.0</cbc:LineExtensionAmount>
Descripción UBL
cbc:LineExtensionAmount
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine. Su atributo
@currencyID se encuentra especificado en el punto 7.
30. Precio de Venta unitario por ítem que modifica y código.
Se consignará el importe del ajuste correspondiente al precio unitario facturado del bien vendido
o servicio vendido.
El precio unitario facturado, es la suma total que quedó obligado a pagar el adquirente o usuario
por cada bien o servicio. Esto incluye los tributos (IGV, ISC, cargos y otros Tributos) y la
deducción de descuentos. Para identificar este valor, se debe de consignar el código “01”
(incluido en el Catálogo No. 16).
En casos de notas de débito que ajusten comprobantes de pago por transferencias gratuitas, de
corresponder, deberá consignarse el monto del ajuste correspondiente al valor referencial
unitario indicado en el numeral 35 del presente documento.
Nota de Débito Electrónica ~ 34 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ubicación
//DebitNote/cac:DebitNoteLine/cac:PricingReference/cac:AlternativeConditionPrice @currencyID
Ejemplo
<cac:PricingReference>
<cac:AlternativeConditionPrice>
<cbc:PriceAmount currencyID="PEN">18.75</cbc:PriceAmount>
<cbc:PriceTypeCode>01</cbc:PriceTypeCode>
</cac:AlternativeConditionPrice>
</cac:PricingReference>
Descripción UBL
cac:PricingReference
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine. Su atributo
@currencyID se encuentra especificado en el punto 7.
cac:PriceTypeCode
Este elemento se encuentra ubicado en el elemento complejo cac:AlternativeConditionPrice y
indica si estamos ante una operación onerosa o no.
Valor de Códigos cbc:ID Catálogo N° 16
Código Descripción
01 Precio unitario (incluye el IGV)
02 Valor referencial unitario en operaciones no onerosas
31. Valor unitario por ítem en operaciones no onerosas y código.
Cuando la transferencia de bienes o de servicios se efectúe gratuitamente, se consignará el
importe del valor de venta unitario que hubiera correspondido a dicho bien o servicio, en
operaciones onerosas con terceros. En su defecto se aplicará el valor de mercado.
Para identificar este valor, se debe de consignar el código “02” (incluido en el Catálogo No. 16).
Ubicación
//DebitNote/cac:DebitNoteLine/cac:PricingReference/cac:AlternativeConditionPrice
Ejemplo
<cac:PricingReference>
<cac:AlternativeConditionPrice>
<cbc:PriceAmount currencyID="PEN">18.75</cbc:PriceAmount>
<cbc:PriceTypeCode>02</cbc:PriceTypeCode>
</cac:AlternativeConditionPrice>
</cac:PricingReference>
Descripción UBL
cac:PricingReference
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine. Su atributo
@currencyID se encuentra especificado en el punto 11. El elemento cbc: PriceTypeCode se
detalla en el numeral anterior.
Nota de Débito Electrónica ~ 35 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
32. Afectación al IGV del ítem que modifica.
Indica si el bien transferido, vendido o cedido en uso, servicio prestado u operación facturable y
materia de ajuste está gravado, exonerado o inafecto al IGV. Se utilizará el Catálogo N° 07:
“Código tipo de afectación del IGV”.
Para el caso peruano los elementos para identificar al tributo contenido en:
../cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/… Adoptarán los valores “1000”, “IGV” y
“VAT” respectivamente.
Ubicación
//DebitNote/cac:DebitNoteLine/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode
<cac:TaxTotal>
<cbc:TaxAmountcurrencyID="PEN">26361.55</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmountcurrencyID="PEN">146453.06</cbc:TaxableAmount>
<cbc:TaxAmountcurrencyID="PEN">26361.55</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Ejemplo
Descripción UBL
cbc:TaxExemptionReasonCode
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Para hacer uso de este
elemento, es necesario además colocar datos que permitan identificar el tributo que se está
informando y el monto del tributo (cbc:TaxAmount), el cual es obligatorio de acuerdo al
estándar UBL. Además, se debe tomar en cuenta que el campo cbc:TaxAmount se consigna a
nivel del cac:TaxTotal y a nivel del cac:TaxSubtotal. En ambos casos se consignará el mismo
valor correspondiente al monto del tributo. Así mismo, en el elemento cbc:TaxableAmount se
informará la base imponible sobre la cual se le aplica el impuesto.
33. Afectación al ISC del ítem que modifica.
S Indica el sistema que fue utilizado para determinar la base imponible cuando el bien
transferido o vendido, materia de ajuste, está gravado con el ISC. Se utilizará el Catálogo No.
08: “Códigos de Tipos de Sistema de Cálculo del ISC”.
Para el caso peruano los elementos para identificar al tributo contenido en:
.../cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/…
Adoptarán los valores “2000”, “ISC” y “EXC” respectivamente.
Nota de Débito Electrónica ~ 36 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Ubicación
//DebitNote/cac:DebitNoteLine/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TierRange
Ejemplo
<cac:TaxTotal>
<cbc:TaxAmountcurrencyID="PEN">8745.00</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmountcurrencyID="PEN">72875.00</cbc:TaxableAmount>
<cbc:TaxAmountcurrencyID="PEN">8745.00</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:TierRange>02</cbc:TierRange>
<cac:TaxScheme>
<cbc:ID>2000</cbc:ID>
<cbc:Name>ISC</cbc:Name>
<cbc:TaxTypeCode>EXC</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
Descripción UBL
cbc:TierRange
Este campo se consigna dentro de un elemento complejo cac:TaxTotal. Para hacer uso de este
elemento, es necesario además colocar datos que permita identificar el tributo que se está
informando y el monto del tributo (cbc:TaxAmount), el cual es obligatorio por de acuerdo al
estándar UBL. Además, se debe tomar en cuenta que el campo cbc:TaxAmount se consigna a
nivel del cac:TaxTotal y a nivel del cac:TaxSubtotal. En ambos casos se consignará el mismo
valor correspondiente al monto del tributo. Así mismo, en el elemento cbc:TaxableAmount se
informará la base imponible sobre la cual se le aplica el impuesto.
34. Descripción detallada del servicio prestado, bien vendido o cedido en uso.
Este elemento es usado solo de corresponder. Consigna la descripción detallada del bien o
servicio.
Otras consideraciones:
 Se deberá colocar el número de serie y/o número de motor, si se trata de un bien
identificable, decorresponder.
 Tratándose de medicamentos y/o insumos para tratamiento de enfermedades
oncológicas y del VIH/SIDA, se consignará adicionalmente la(s) partida(s) arancelaria(s)
correspondiente(s).
No obligatorio cuando el tipo de nota de débito (numeral 7) es 02.
Ubicación
//DebitNote/cac:DebitNoteLine/cac:Item/cbc:Description
Ejemplo
<cac:Item>
<cbc:Description><![CDATA[Ajuste por ajustes de precios]]></cbc:Description>
…
</cac:Item>
Nota de Débito Electrónica ~ 37 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Descripción UBL
cbc:Description
Este campo se encuentra ubicado en el elemento complejo cac:DebitNoteLine, aquí se detalla
en forma detallada la descripción del ítem que se está vendiendo
35. Código Producto.
Código del producto de acuerdo al tipo de codificación interna que se utilice.
Su uso será obligatorio si el emisor electrónico, opta por consignar este código, en reemplazo de
la descripción detallada. Para tal efecto el código a usar será aquél, que las normas que regulan
el llevado de libros y registros, denominan como código de existencia.
Ubicación
//DebitNote/cac:DebitNoteLine/cac:Item/cac:SellersItemIdentification/cbc:ID
Ejemplo
<cac:Item>
…
<cac:SellersItemIdentification>
<cbc:ID>Cap-258963</cbc:ID>
</cac:SellersItemIdentification>
…
</cac:Item>
Descripción UBL
cac:SellersItemIdentification
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine.
36. Código Producto de SUNAT.
Código del producto de acuerdo al estándar internacional de la ONU denominado: United Nations
Standard Products and Services Code - Código de productos y servicios estándar de las Naciones
Unidas - UNSPSC v14_0801, a que hace referencia el catálogo N° 15 del Anexo N° 8 de la
Resolución de Superintendencia N° 097-2012/SUNAT y modificatorias.
Ubicación
//DebitNote/cac:DebitNoteLine/cac:Item/cac:CommodityClassification/cbc:ItemClassificationCode
Ejemplo
<cac:Item>
…
<cac:CommodityClassification>
<cbc:ItemClassificationCode listID="UNSPSC" listAgencyName="GS1 US" listName="Item
Classification">51121703</cbc:ItemClassificationCode>
</cac:CommodityClassification>
…
</cac:Item>
Nota de Débito Electrónica ~ 38 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
Descripción UBL
cbc:ItemClassificationCode
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine.
Atributos
listID "UNSPSC"
listAgencyName "GS1 US"
listName "Item Classifi cation"
Valor de Códigos cbc:ID Catálogo N° 25
Código Descripción
43233201 Software de Servicios de Autenticación
80101511 Servicio de asesoramiento en recursos humanos
50201706 Café
El resto de códigos se pueden consultar en el siguiente link: Clic Aquí.
37. Valor unitario del ítem.
Obligatorio. Se consignará el importe correspondiente al valor o monto unitario del bien
vendido, cedido o servicio prestado, indicado en una línea o ítem de la factura. Este importe no
incluye los tributos (IGV, ISC y otros Tributos) ni los cargos globales. Ubicación
//DebitNote/cac:DebitNoteLine/cac:Price/cbc:PriceAmount
Ejemplo
<DebitNote>
…
<cac:Price>
<cbc:PriceAmount currencyID="PEN">678.0</cbc:PriceAmount>
</cac:Price>
…
</DebitNote>
Descripción UBL
cbc:PriceAmount
Este elemento se encuentra ubicado en el elemento complejo cac:DebitNoteLine
Nota de Débito Electrónica ~ 39 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
B.2 Detalle de elementos complejos
En esta sección se describe aquellos tag que por su complejidad requieren de una mayor
explicación.
B.2.1 Tag UBLExtension
Uno o más <ext:UBLExtension> están contenidos dentro de un elemento
<ext:UBLExtensions> descendiente directo del elemento raíz del documento. Estos elementos
están disponibles en UBL 2.1 para la inclusión de datos no [UBL], como es nuestro caso.
Se hará uso de este tipo de componente de extensión para especificar solamente la firma digital.
1. ext:UBLExtension/ext:ExtensionContent/ds:Signature
No es objeto de este informe especificar el tipo de firma que se utilizará en el contexto de la
factura electrónica, sin embargo se sientan las bases para declarar un certificado y se tomará
como ejemplo una firma sencilla XMLdSig.
La firma digital será alojada dentro del elemento <ext:UBLExtension>
 ExtensionContent. Dentro de éste elemento es donde se incluyen las firmas [XMLDSig] de
todos los firmantes del documento. Por tanto, en el documento únicamente habrá un solo
<ext:UBLExtension> para la inclusión de firmas.
 La firma se realizará sobre el documento completo y podrá llevarse a cabo con un
componente propio o externo de firma de documentos XML. En cualquier caso la firma
satisfará como mínimo los requerimientos de “Firma Electrónica”. Se deberá utilizar
[XMLDSig].
 Se utilizará para firmar la clave privada de un certificado digital X509 válido no vencido. Se
firma todo el documento (nodo raíz). En esta implementación no podrán añadirse nuevos
datos al documento después de firmar, ni siquiera extensiones en el formato acordado,
puesto que la validación fallaría.
 Puesto que una firma digital XML es un proceso matemático por el que los datos a firmar se
transforman siguiendo una serie de reglas y cálculos basados en una clave y cuyos
resultados son guardados en elementos XML y adjuntados o no a los datos primitivos del
proceso, en el estándar [XMLDSig12] encontramos:
o Definición de la estructura XML en la que almacenar la firma
o Definición del proceso de firma
o Definición del proceso de validación de firma
o Agrupación y aceptación de los algoritmos y procesos para la transformación en
forma canónica de los datos firmados y de la firma
o Agrupación y aceptación de los algoritmos y procesos de transformación para la
obtención de la firma
A continuación se mencionan el detalle de los elementos de la extensión:
 ds:Signature: Es un elemento simple que contiene información de lo que se está
firmando, la propia firma, las claves utilizadas para firmar. A continuación veremos
1
El esquema de datos XML del estándar puede encontrarse en: http://www.w3.org/TR/xmldsig-core/
Nota de Débito Electrónica ~ 40 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
sus atributos y elementos uno por uno:
El atributo Id es opcional pero es muy útil para identificar la firma dentro de un documento,
sobre todo cuando se trabaja con firmas múltiples.
Por ejemplo: <ds:Signature Id="signatureKG">
i. ds:SignedInfo: Este elemento puede dividirse en dos partes desde el punto de
vista conceptual: información sobre el valor de la firma e información sobre los datos
a firmar.
1. ds:CanonicalizationMethod: Posee un atributo Algorithm que
indica cómo se debe transformar a
forma canónica el elemento <ds:SignedInfo> antes de
realizar la firma.
Distintos XML pueden diferir en su forma de ser escritos y sin embargo
significar lo mismo. Como la firma se realiza a nivel de bytes, aunque un
documento signifique lo mismo y tenga la misma información que otro, ambos
pueden tener firmas diferentes si no están escritos exactamente igual. Habrá
que elegir entre una de todas las formas posibles de escribir un documento
XML, la forma canónica, y transformar los documentos a esta forma sin que
su información y significado se vean alterados.
A este proceso se le llama transformación en forma canónica. Habrá varias
formas canónicas dependiendo del algoritmo que se utilice. Dos documentos
Nota de Débito Electrónica ~ 41 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
están en la misma forma canónica si los algoritmos utilizados para su
obtención son equivalentes.
2. ds: SignatureMethod: Especifica qué tipo de algoritmo de firma se
utilizará para obtener la firma. La firma se realiza aplicando este
algoritmo matemático sobre el elemento <ds:SignedInfo> que,
puesto que contiene los valores hash de los distintos datos que se
quieren firmar –como se verá a continuación-, será diferente en cada
caso.
3. ds: Reference: Cada elemento Reference incluye el hash de un
objeto de datos y las transformaciones aplicadas a ese objeto para
producir dicho hash. El atributo URI (<ds:Reference
URI="">)identifica al objeto de datos que se va a firmar. Éste puede
ser un objeto fuera del documento en el que está la firma o bien un
objeto dentro del propio documento. Si su valor es cadena vacía
identifica al documento completo que contiene la firma. Por
supuesto puede haber varios
<ds:Reference> permitiendo a una misma firma [XMLDSig] cubrir múltiples
objetos.
ds:Transforms: es opcional aunque es el elemento con más
fuerza de <ds:Reference>.Si aparece, contendrá una lista de
<ds:Transform> en la que cada uno de sus elementos indica un
paso realizado en el procesamiento de cálculo del hash. Cada paso
tiene como entrada la salida del anterior y puede incluir operaciones
como transformación en forma canónica,
codificación/decodificación, transformaciones XSL, validación de
esquemas, etc. La salida del último <ds:Transform> es la entrada
de la función de cálculo del hash.
Al permitir que se puedan firmar distintas porciones de un
documento, las modificaciones posteriores a la firma de las
porciones no incluidas no afectarán en nada a la validación de la
firma.
ds:DigestMethod: Define la función hash utilizada a través del
atributo Algorithm.
ds: DigestValue: Es el valor hash codificado en Base64.
Nota de Débito Electrónica ~ 42 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
ii. ds:SignatureValue: contiene la firma codificada en Base64. La firma es el resultado
de una serie de transformaciones sobre los datos binarios del elemento
<ds:SignedInfo>. El elemento <ds:SignatureValue> contiene este valor binario de
la firma codificado en Base64.
iii. ds: KeyInfo: Es una estructura opcional que identifica al firmante. Su contenido
suele utilizarse en procesos de verificación de firmas, de ahí la importancia de que
lo que se incluya en su interior sean los elementos de:
1. ds:X509Data: Contiene información del certificado firmante.
2. ds: KeyValue: Contiene información de la clave pública.
La información que proporciona <ds:KeyInfo> en todos sus elementos debe
corresponder al mismo certificado o clave.
En caso de no incluir la estructura <ds:KeyInfo>, la firma no podría considerarse
como “Firma Electrónica Avanzada” puesto que el firmante no podría ser
identificado.
Nota de Débito Electrónica ~ 43 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
| 1.5  | Ejemplos de casos identificados  |     |     |     |     |     |
| ---- | -------------------------------- | --- | --- | --- | --- | --- |

A. Nota de Débito sobre Factura
La empresa Soporte Tecnológicos EIRL, cuyo nombre comercial es “Tu soporte”, identificada
con RUC 20100454523, debe emitir la nota de débito electrónica N° FD01-211 con la siguiente
información:

| FechadeEmisión  |     | : 25 de junio del 2017  |     |     |     |     |
| --------------- | --- | ----------------------- | --- | --- | --- | --- |
Adquirente o Usuario  : Servicabinas S.A.
| RUC  |     | : 20587896411  |     |     |     |     |
| ---- | --- | -------------- | --- | --- | --- | --- |
Motivo  : Ampliación de garantía de memoria DDR-3B1333Kingston

Código de Software de Facturación: 0501002017062500125
Código de Establecimiento Anexo: 0001

Devolución de mercadería vendida:

|         | Código  | Unidad de  |           |              | Afectación  | Precio    |
| ------- | ------- | ---------- | --------- | ------------ | ----------- | --------- |
| Código  |         |            | Cantidad  | Descripción  |             |           |
|         | SUNAT   | Medida     |           |              | al IGV      | Unitario  |
Ampliación de garantía de
MPC35  32101622  Unidad  250  memoria  DDR-B1333  Gravado  5.00
Kingston

| Valor Unitario  |       |           | Valor         |                     |          |     |
| --------------- | ----- | --------- | ------------- | ------------------- | -------- | --- |
|                 |       | Cantidad  |               | IGV  Importe Total  |          |     |
|                 | (1)   |           | venta item    |                     |          |     |
|                 | 4.24  |           | 250  1059.32  | 190.68              | 1250.00  |     |

(1) s/. 5/1.18= 4.24
|   Nota de Débito Electrónica   |     |     |     |     |     | ~ 44 ~  |
| ------------------------------ | --- | --- | --- | --- | --- | ------- |

Guía de elaboración de documentos electrónicos XML - UBL 2.1

REQUISITO  CASO 1
| Fecha de emission  |     | 25/06/2017  |
| ------------------ | --- | ----------- |
Firma Digital

Apellidos y nombres o denominación o razón social  Soporte Tecnológicos EIRL
| Nombre Comercial  |     | “Tu Soporte”  |
| ----------------- | --- | ------------- |
| Número de RUC     |     | 20100454523   |
Código del tipo de Nota de débito electrónica  07
Numeración, conformada por serie y número correlativo  FD01-211
| Tipo y número de documento de identidad del  |     | 20587896411  |
| -------------------------------------------- | --- | ------------ |
adquirente o usuario
Apellidos y nombres o denominación o razón social del  Servicabinas S.A.
adquirente o usuario
Motivo o sustento  Ampliación de garantía de memoria DDR-B1333 Kingston
| Número de orden del Ítem                    |           | 1     |
| ------------------------------------------- | --------- | ----- |
| Unidad de medida por ítem que modifica      |           | ZZ    |
| Cantidad de unidades por ítem que modifica  |           | 1     |
| Código de producto                          | MPC35     |       |
| Código de producto SUNAT                    | 32101622  |       |
Descripción detallada del bien vendido o cedido en uso,  Ampliación de garantía de 6 a 12meses de
Memoria DDR-3 B1333
descripción o tipo de servicio prestado por ítem
Kingston
| Valor unitario por ítem que modifica                  | 4.24     |            |
| ----------------------------------------------------- | -------- | ---------- |
| Precio de venta unitario por ítem que modifica        | 5.00     |            |
| Afectación al IGV por ítem que modifica               |          | 10         |
| IGV del ítem                                          | 190.68   |            |
| Sistema de ISC por ítem que modifica                  |          |            |
| Valor de venta por ítem                               | 1059.32  |            |
| Total valor de venta  - operaciones gravadas          |          | 1059.32    |
| Total valor de venta  - operaciones inafectas         |          |            |
| Total valor de venta - operaciones exoneradas         |          |            |
| Sumatoria IGV                                         |          | 190.68     |
| Sumatoria ISC                                         |          |            |
| Sumatoria otros tributes                              |          |            |
| Sumatoria otros Cargos                                |          |            |
| Total descuentos                                      |          |            |
| Importe total                                         |          | 1250.00    |
| Versión del UBL                                       |          |            |
| Versión de la estructura del documento                |          |            |
| Tipo de moneda en la cual se emite la nota de débito  |          | PEN        |
| Serie y número del documento que modifica             |          | F001-4355  |
Tipo de documento que modifica  01
| Documento de referencia  |     |     |
| ------------------------ | --- | --- |
0001
Código de Establecimiento

|   Nota de Débito Electrónica   |     | ~ 45 ~  |
| ------------------------------ | --- | ------- |

Guía de elaboración de documentos electrónicos XML - UBL 2.1
<?xml version="1.0" encoding="ISO-8859-1" standalone="no"?>
<DebitNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2"
xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
xmlns:ccts ="urn:un:unece:uncefact:documentation:2"
xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2"
xmlns:sac="urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1"
xmlns:udt="urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<ext:UBLExtensions>
<ext:UBLExtension>
<ext:ExtensionContent>
<ds:Signature Id="SignST">
<ds:SignedInfo>
<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-
20010315"/>
<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
<ds:Reference URI="">
<ds:Transforms>
<ds:Transform
Algorithm="http://www.w3.org/2000/09/xmldsig#envelopedsignature"/>
</ds:Transforms>
<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
<ds:DigestValue>uy0/8Pg/62e+GIQ0ZRVRRCWmPBk=</ds:DigestValue>
</ds:Reference>
</ds:SignedInfo>
<ds:SignatureValue>
silqLF655RAWmwtd5llBQ2VqVa4gZus7e53ChvMBtXw+HOyR6oNPySTJKnrCZ0kRpfN3i3OgLlyC
b+Xfm9OlVOrVaYv0W4NM10hKrdCfWDxnGzhOxoXbqFL+jmRlhBsEQ+R6lcg9ctn60jyDWm+LtRR7
By6xzluFqdR0C5OtaiU=</ds:SignatureValue>
<ds:KeyInfo>
<ds:X509Data>
<ds:X509SubjectName>
1.2.840.113549.1.9.1=#161a4253554c434140534f55544845524e504552552e434f4d2e5045,
CN=Juan Robles,OU=20889666312,O= SOPORTE TECNOLOGICO EIRL,L=LIMA,ST=LIMA,
C=PE</ds:X509SubjectName>
<ds:X509Certificate>
MIIESTCCAzGgAwIBAgIKWOCRzgAAAAAAIjANBgkqhkiG9w0BAQUFADAnMRUwEwYKCZImiZPyLGQB
GRYFU1VOQVQxDjAMBgNVBAMTBVNVTkFUMB4XDTEwMTIyODE5NTExMFoXDTExMTIyODIwMDExMFow
gZUxCzAJBgNVBAYTAlBFMQ0wCwYDVQQIEwRMSU1BMQ0wCwYDVQQHEwRMSU1BMREwDwYDVQQKEwhT
T1VUSEVSTjEUMBIGA1UECxMLMjAxMDAxNDc1MTQxFDASBgNVBAMTC0JvcmlzIFN1bGNhMSkwJwYJ
KoZIhvcNAQkBFhpCU1VMQ0FAU09VVEhFUk5QRVJVLkNPTS5QRTCBnzANBgkqhkiG9w0BAQEFAAOB ~ 46
~
jQAwgYkCgYEAtRtcpfBLzyajuEmYt4mVH8EE02KQiETsdKStUThVYM7g3Lkx5zq3SH5nLH00EKGC
tota6RR+V40sgIbnh+Nfs1SOQcAohNwRfWhho7sKNZFR971rFxj4cTKMEvpt8Dr98UYFkJhph6Wn
sniGM2tJDq9KJ52UXrlScMfBityx0AsCAwEAAaOCAYowggGGMA4GA1UdDwEB/wQEAwIE8DBEBgkq
hkiG9w0BCQ8ENzA1MA4GCCqGSIb3DQMCAgIAgDAOBggqhkiG9w0DBAICAIAwBwYFKw4DAgcwCgYI
KoZIhvcNAwcwHQYDVR0OBBYEFG/m6twbiRNzRINavjq+U0j/sZECMBMGA1UdJQQMMAoGCCsGAQUF
BwMCMB8GA1UdIwQYMBaAFN9kHQDqWONmozw3xdNSIMFW2t+7MFkGA1UdHwRSMFAwTqBMoEqGImh0
dHA6Ly9wY2IyMjYvQ2VydEVucm9sbC9TVU5BVC5jcmyGJGZpbGU6Ly9cXHBjYjIyNlxDZXJ0RW5y
b2xsXFNVTkFULmNybDB+BggrBgEFBQcBAQRyMHAwNQYIKwYBBQUHMAKGKWh0dHA6Ly9wY2IyMjYv
M3abGsOE53wfxqQF5uf/jkzZA9hbLHtE1aLKBD0Mhzc6cvI072alnE6QU3RZ16ie9CYsHmMrs+sP
HMy8DJU5YrdnqHdSn2D3nhKBi4QfT/WURPOuo6DF4iWgrCyMf3eJgmGKSUN3At5fK4HSpfyURT0k
boaJKNBgQwy0HhGh5BLM7DsTi/KwfdUYkoFgrY71Pm23+ra+xTow1Vk9gj5NqrlpMY5gAVQXEIo1
++GxDtaK/5EiVKSqzJ6geIfz</ds:X509Certificate>
</ds:X509Data>
</ds:KeyInfo>
</ds:Signature>
</ext:ExtensionContent>
</ext:UBLExtension>
</ext:UBLExtensions>
Nota de Débito Electrónica ~ 46 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
<cbc:UBLVersionID>2.1</cbc:UBLVersionID>
<cbc :CustomizationID>2.0</cb c:CustomizationID>
<cbc:ID>FD01-211</cbc:ID>
<cbc:IssueDate>2017-06-25</cbc:IssueDate>
<cbc:IssueTime>16:44:51</cbc:IssueTime>
<cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
<cbc:Note languageLocaleID="3000">0501002017062500125</cbc:Note>
<cac:DiscrepancyResponse>
<cbc:ReferenceID>F001-4355</cbc:ReferenceID>
<cbc:ResponseCode>02</cbc:ResponseCode>
<cbc:Description> Ampliación garantía de memoria DDR-3 B1333
Kingston</cbc:Description>
</cac:DiscrepancyResponse>
<cac:BillingReference>
<cac:InvoiceDocumentReference>
<cbc:ID>F001-4355</cbc:ID>
<cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>
</cac:InvoiceDocumentReference>
</cac:BillingReference>
<cac:Signature>
<cbc:ID>IDSignST</cbc:ID>
<cac:SignatoryParty>
<cac:PartyIdentification>
<cbc:ID>20100454523</cbc:ID>
</cac:PartyIdentification>
<cac:PartyName>
<cbc:Name>SOPORTE TECNOLOGICOS EIRL</cbc:Name>
</cac:PartyName>
</cac:SignatoryParty>
<cac:DigitalSignatureAttachment>
<cac:ExternalReference>
<cbc:URI>#SignatureSP</cbc:URI>
</cac:ExternalReference>
</cac:DigitalSignatureAttachment>
</cac:Signature>
<cac:AccountingSupplierParty>
<cac:Party>
<cac:PartyName>
<cbc:Name>Tu Soporte</cbc:Name>
</cac:PartyName>
<cac:PartyTaxScheme>
<cbc:RegistrationName>
<![CDATA[Soporte Tecnológicos EIRL]]></cbc:RegistrationName>
~ 47
<CompanyID schemeID="6" schemeName="SUNAT:Identificador de Documento de
~
Identidad" schemeAgencyName="PE:SUNAT"
schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20100454523</CompanyID>
<cac:RegistrationAddress>
<cbc:AddressTypeCode>0001</cbc:AddressTypeCode>
</cac:RegistrationAddress>
<cac:TaxScheme>
<cbc:ID>-</cbc:ID>
</cac:TaxScheme>
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingSupplierParty>
<cac:AccountingCustomerParty>
<cac:Party>
<cac:PartyTaxScheme>
<cbc:RegistrationName>Servicabinas S.A.</cbc:RegistrationName>
<CompanyID schemeID="6" schemeName="SUNAT:Identificador de Documento de
Identidad" schemeAgencyName="PE:SUNAT"
schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">20587896411</CompanyID>
Nota de Débito Electrónica ~ 47 ~

Guía de elaboración de documentos electrónicos XML - UBL 2.1
<cac:Tax Scheme>
<cbc:ID>-</cbc:ID>
</cac:TaxScheme>
</cac:PartyTaxScheme>
</cac:Party>
</cac:AccountingCustomerParty>
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">190.68</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">1059.32</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">190.68</cbc:TaxAmount>
<cac:TaxCategory>
<cac:TaxScheme>
<cbc:ID schemeID="UN/ECE 5153" schemeAgencyID="6">1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>
</cac:TaxScheme>
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<cac:LegalMonetaryTotal>
<cbc:PayableAmount currencyID="PEN">1250.00</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
<cac:DebitNoteLine>
<cbc:ID>1</cbc:ID>
<cbc:DebitedQuantity unitCode="ZZ">1</cbc:DebitedQuantity>
<cbc:LineExtensionAmount currencyID="PEN">1059.32</cbc:LineExtensionAmount>
<cac:PricingReference>
<cac:AlternativeConditionPrice>
<cbc:PriceAmount currencyID="PEN">5.00</cbc:PriceAmount>
<cbc:PriceTypeCode>01</cbc:PriceTypeCode>
</cac:AlternativeConditionPrice>
</cac:PricingReference>
<cac:TaxTotal>
<cbc:TaxAmount currencyID="PEN">190.68</cbc:TaxAmount>
<cac:TaxSubtotal>
<cbc:TaxableAmount currencyID="PEN">1059.32</cbc:TaxableAmount>
<cbc:TaxAmount currencyID="PEN">190.68</cbc:TaxAmount>
<cac:TaxCategory>
<cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>
<cac:TaxScheme>
<cbc:ID>1000</cbc:ID>
<cbc:Name>IGV</cbc:Name>
<cbc:TaxTypeCode>VAT</cbc:TaxTypeCode> ~ 48
</cac:TaxScheme> ~
</cac:TaxCategory>
</cac:TaxSubtotal>
</cac:TaxTotal>
<cac:Item>
<cbc:Description> Ampliación de garantía de 6 a 12 meses de Memoria DDR-3 B1333
Kingston</cbc:Description>
<cac:SellersItemIdentification>
<cbc:ID> MPC35</cbc:ID>
</cac:SellersItemIdentification>
<cac:CommodityClassification>
<cbc:ItemClassificationCode listID="UNSPSC" listAgencyName="GS1 US"
listName="Item Classification">32101622</cbc:ItemClassificationCode>
</cac:CommodityClassification>
</cac:Item>
<cac:Price>
<cbc:PriceAmount currencyID="PEN">4.24</cbc:PriceAmount>
</cac:Price>
</cac:DebitNoteLine>
</DebitNote>
Nota de Débito Electrónica ~ 48 ~