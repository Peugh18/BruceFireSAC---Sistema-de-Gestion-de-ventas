<?php

namespace App\Http\Controllers;

use App\Actions\Tecnico\GuardarEvidencia;
use App\Models\Evidencia;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Tecnico\ConversacionDeLaOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mensajes y evidencias de la orden (Fase D). Los usan el vendedor, los
 * técnicos y el Gerente; cada uno solo en las órdenes que puede ver.
 */
class ConversacionOrdenController extends Controller
{
    private const TIPOS_DE_ARCHIVO = 'jpg,jpeg,png,webp,heic,heif,gif,mp3,m4a,mp4,ogg,oga,wav,webm,weba,aac,pdf,doc,docx,xls,xlsx,txt';

    public function mensaje(Request $request, Team $current_team, ServiceOrder $service_order, GuardarEvidencia $guardar): RedirectResponse
    {
        $this->autorizar($request, $service_order);

        $datos = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:500', 'required_without:archivo'],
            'equipment_id' => ['nullable', 'integer'],
            'archivo' => ['nullable', 'file', 'max:15360', 'mimes:'.self::TIPOS_DE_ARCHIVO],
        ], [
            'mensaje.required_without' => 'Escribe un mensaje o adjunta un archivo.',
            'archivo.max' => 'El archivo pesa más de 15 MB.',
            'archivo.mimes' => 'Ese tipo de archivo no se puede adjuntar.',
        ]);

        $equipmentId = $datos['equipment_id'] ?? null;
        abort_if($equipmentId !== null && ! $service_order->equipments()->whereKey($equipmentId)->exists(), 422, 'Ese extintor no es de esta orden.');

        DB::transaction(function () use ($request, $service_order, $datos, $equipmentId, $guardar) {
            $evento = $service_order->events()->create([
                'tipo' => 'otro',
                'user_id' => $request->user()->id,
                'equipment_id' => $equipmentId,
                'payload' => [
                    'accion' => 'mensaje',
                    'mensaje' => $datos['mensaje'] ?? null,
                    'origen' => ConversacionDeLaOrden::origenDe($request->user()),
                ],
                'created_at' => now(),
            ]);

            if ($request->hasFile('archivo')) {
                $guardar->archivo($service_order, $request->file('archivo'), 'conversacion', $request->user(), $equipmentId, $evento);
            }

            $service_order->touch();
        });

        return back();
    }

    /**
     * Sube una foto, un audio o un archivo suelto de la orden (antes, después,
     * deficiencia…), con su equipo opcional.
     */
    public function guardarEvidencia(Request $request, Team $current_team, ServiceOrder $service_order, GuardarEvidencia $guardar): RedirectResponse
    {
        $this->autorizar($request, $service_order);

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'max:15360', 'mimes:'.self::TIPOS_DE_ARCHIVO],
            'etapa' => ['required', 'in:'.implode(',', Evidencia::ETAPAS)],
            'equipment_id' => ['nullable', 'integer'],
        ]);

        $equipmentId = $datos['equipment_id'] ?? null;
        abort_if($equipmentId !== null && ! $service_order->equipments()->whereKey($equipmentId)->exists(), 422, 'Ese extintor no es de esta orden.');

        $guardar->archivo($service_order, $request->file('archivo'), $datos['etapa'], $request->user(), $equipmentId);

        return back()->with('success', 'Evidencia guardada.');
    }

    /**
     * Sirve el archivo con sesión: nunca es una URL pública.
     */
    public function evidencia(Request $request, Team $current_team, Evidencia $evidencia): StreamedResponse
    {
        $this->autorizar($request, $evidencia->serviceOrder);
        abort_unless(Storage::disk('local')->exists($evidencia->path), 404);

        // Solo imágenes, audio, video y PDF se muestran en el navegador; lo
        // demás se descarga, para que un archivo subido no corra como página.
        $seguros = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm', 'video/mp4', 'video/webm', 'application/pdf'];
        $esSeguro = in_array($evidencia->mime, $seguros, true);

        return Storage::disk('local')->response($evidencia->path, $evidencia->nombre_original, [
            'Content-Type' => $esSeguro ? $evidencia->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, max-age=3600',
        ], $esSeguro ? 'inline' : 'attachment');
    }

    protected function autorizar(Request $request, ServiceOrder $orden): void
    {
        abort_unless(ConversacionDeLaOrden::puedeParticipar($request->user(), $orden), 404);
    }
}
