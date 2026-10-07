<?php

namespace App\Services\Billing;

use App\Contracts\GreClientInterface;
use App\Models\CompanySetting;
use App\Models\DispatchGuide;
use App\Models\DispatchGuideItem;
use Greenter\Model\Client\Client as GreenterClient;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver as GreenterDriver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle as GreenterVehicle;
use Greenter\Model\Sale\Document;
use Greenter\See;
use Greenter\Zip\ZipFly;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Arma, firma y envía la guía de remisión (tipo 09, serie T001) por la API
 * REST de SUNAT. La guía queda "lista para trasladar" solo con el CDR
 * aceptado (docs/ai/SUNAT.md §5).
 */
class GuiaRemisionService
{
    public function __construct(protected GreClientInterface $cliente) {}

    public function build(DispatchGuide $guia): Despatch
    {
        $guia->loadMissing('items', 'vehiculo', 'conductor');
        $empresa = CompanySetting::current();

        $company = (new Company)
            ->setRuc($empresa->ruc)
            ->setRazonSocial($empresa->razon_social)
            ->setNombreComercial($empresa->nombre_comercial ?? $empresa->razon_social);

        $envio = (new Shipment)
            ->setCodTraslado($guia->motivo)
            ->setDesTraslado($guia->motivo_descripcion ?? DispatchGuide::MOTIVOS[$guia->motivo] ?? null)
            ->setModTraslado($guia->modalidad)
            ->setFecTraslado($guia->fecha_traslado->toDateTime())
            ->setPesoTotal((float) $guia->peso_bruto)
            ->setUndPesoTotal('KGM')
            ->setPartida($this->direccion($guia->partida_ubigeo, $guia->partida_direccion, $guia->partida_cod_establecimiento, $empresa->ruc))
            ->setLlegada($this->direccion($guia->llegada_ubigeo, $guia->llegada_direccion, $guia->llegada_cod_establecimiento, $empresa->ruc));

        if ($guia->modalidad === '02') {
            if ($guia->vehiculo === null || $guia->conductor === null) {
                throw new RuntimeException('El transporte privado necesita vehículo y conductor.');
            }
            $envio->setVehiculo((new GreenterVehicle)->setPlaca($guia->vehiculo->placa))
                ->setChoferes([
                    (new GreenterDriver)
                        ->setTipo('Principal')
                        ->setTipoDoc('1')
                        ->setNroDoc($guia->conductor->dni)
                        ->setLicencia($guia->conductor->licencia)
                        ->setNombres($guia->conductor->nombres)
                        ->setApellidos($guia->conductor->apellidos),
                ]);
        } else {
            $envio->setTransportista(
                (new Transportist)->setTipoDoc('6')->setNumDoc((string) $guia->transportista_ruc)->setRznSocial((string) $guia->transportista_razon)
            );
        }

        $despatch = (new Despatch)
            ->setVersion('2022')
            ->setTipoDoc('09')
            ->setSerie($guia->serie)
            ->setCorrelativo((string) $guia->correlativo)
            ->setFechaEmision($guia->fecha_emision->toDateTime())
            ->setCompany($company)
            ->setDestinatario(
                (new GreenterClient)
                    ->setTipoDoc($guia->destinatario_tipo_doc)
                    ->setNumDoc($guia->destinatario_num_doc)
                    ->setRznSocial($guia->destinatario_nombre)
            )
            ->setEnvio($envio)
            ->setDetails($guia->items->map(fn (DispatchGuideItem $item): DespatchDetail => (new DespatchDetail)
                ->setCodigo($item->codigo)
                ->setDescripcion($item->descripcion)
                ->setUnidad($item->unidad)
                ->setCantidad((float) $item->cantidad))->values()->all());

        if ($guia->doc_relacionado_tipo !== null && $guia->doc_relacionado_numero !== null) {
            $despatch->setRelDoc(
                (new Document)->setTipoDoc($guia->doc_relacionado_tipo)->setNroDoc($guia->doc_relacionado_numero)
            );
        }

        return $despatch;
    }

    /**
     * Firma el XML, lo comprime y lo envía. Queda "enviada" con su ticket; el
     * CDR llega al consultar.
     */
    public function enviar(DispatchGuide $guia): DispatchGuide
    {
        if ($guia->estado_sunat === DispatchGuide::ACEPTADA || $guia->sunat_ticket !== null && $guia->estado_sunat === DispatchGuide::ENVIADA) {
            return $guia;
        }

        $despatch = $this->build($guia);
        $xml = $this->firmar($despatch);
        $nombre = config('billing.sunat.ruc').'-09-'.$guia->serie.'-'.$guia->correlativo;
        $zip = (string) (new ZipFly)->compress($nombre.'.xml', $xml);

        Storage::disk('local')->put("gre/xml/{$nombre}.xml", $xml);

        $ticket = $this->cliente->enviar($nombre, $zip);

        $guia->update([
            'xml_path' => "gre/xml/{$nombre}.xml",
            'hash_zip' => hash('sha256', $zip),
            'sunat_ticket' => $ticket,
            'estado_sunat' => DispatchGuide::ENVIADA,
            'enviado_at' => now(),
        ]);

        return $guia->refresh();
    }

    /**
     * Consulta el ticket. Con código 0 y CDR queda aceptada ("lista para
     * trasladar"); con 98 sigue en proceso; con 99, rechazada. Cualquier otra
     * respuesta —incluido un error de comunicación— no dice nada sobre el
     * destino de la guía: queda «enviada» y se vuelve a consultar.
     */
    public function consultar(DispatchGuide $guia): DispatchGuide
    {
        if ($guia->sunat_ticket === null || $guia->estado_sunat !== DispatchGuide::ENVIADA) {
            return $guia;
        }

        $respuesta = $this->cliente->consultar($guia->sunat_ticket);

        if ($respuesta['en_proceso']) {
            return $guia;
        }

        if ($respuesta['aceptada'] && $respuesta['cdr_zip'] !== null) {
            $cdrPath = 'gre/cdr/R-'.config('billing.sunat.ruc').'-09-'.$guia->serie.'-'.$guia->correlativo.'.zip';
            Storage::disk('local')->put($cdrPath, $respuesta['cdr_zip']);
            $guia->update(['estado_sunat' => DispatchGuide::ACEPTADA, 'cdr_path' => $cdrPath, 'sunat_codigo' => $respuesta['codigo'], 'sunat_mensaje' => $respuesta['mensaje']]);

            return $guia->refresh();
        }

        if ($respuesta['codigo'] === '99') {
            $guia->update(['estado_sunat' => DispatchGuide::RECHAZADA, 'sunat_codigo' => $respuesta['codigo'], 'sunat_mensaje' => $respuesta['mensaje']]);

            return $guia->refresh();
        }

        // Sin respuesta concluyente no hay rechazo: la guía sigue «enviada» y
        // se consulta otra vez más adelante. El código de SUNAT no cambia
        // porque no hubo ninguno.
        $guia->update(['sunat_mensaje' => $respuesta['mensaje']]);

        return $guia->refresh();
    }

    protected function firmar(Despatch $despatch): string
    {
        $certPath = config('billing.sunat.cert_path');

        if (! is_string($certPath) || ! file_exists($certPath)) {
            throw new RuntimeException('Certificado SUNAT no configurado (billing.sunat.cert_path).');
        }

        $see = new See;
        $see->setCertificate((string) file_get_contents($certPath));
        $xml = $see->getXmlSigned($despatch);

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Greenter no pudo generar el XML firmado de la guía.');
        }

        return $xml;
    }

    protected function direccion(string $ubigeo, string $direccion, ?string $codLocal, ?string $ruc): Direction
    {
        $direction = new Direction($ubigeo, $direccion);

        // Un local propio lleva el RUC de la empresa y su código de establecimiento anexo.
        if ($ruc !== null && $codLocal !== null) {
            $direction->setRuc($ruc)->setCodLocal($codLocal);
        }

        return $direction;
    }
}
