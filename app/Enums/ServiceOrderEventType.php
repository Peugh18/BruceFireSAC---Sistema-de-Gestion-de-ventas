<?php

namespace App\Enums;

enum ServiceOrderEventType: string
{
    case Creada = 'creada';
    case Recibida = 'recibida';
    case DeficienciaDetectada = 'deficiencia_detectada';
    case NotificacionVendedor = 'notificacion_vendedor';
    case AutorizacionRegistrada = 'autorizacion_registrada';
    case TrabajoCompletado = 'trabajo_completado';
    case EntregaRegistrada = 'entrega_registrada';
    case Otro = 'otro';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
