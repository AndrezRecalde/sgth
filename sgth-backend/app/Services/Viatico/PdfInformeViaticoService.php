<?php

namespace App\Services\Viatico;

use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Models\Viatico\Viatico;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PdfInformeViaticoService
{
    public function generarEnlaceTemporal(int $viaticoId): string
    {
        $viatico = Viatico::with([
            'servidor.puesto.unidadAdministrativa',
            'comision',
            'destinos',
            'transportes.ciudadOrigen',
            'transportes.ciudadDestino',
            'liquidacion.detallesFactura'
        ])->findOrFail($viaticoId);

        $estadosPermitidos = [
            EstadoViatico::PENDIENTE_LIQUIDACION->value,
            EstadoViatico::LIQUIDADO->value,
            EstadoViatico::CONTABILIZADO->value
        ];

        if (!in_array($viatico->estado->value, $estadosPermitidos)) {
            throw new ReglaNegocioException('El viático no se encuentra en un estado que permita descargar el informe.');
        }

        $nombreArchivo = "informe_{$viatico->codigo_viatico}.pdf";
        $path = "informes-viatico/{$nombreArchivo}";

        $pdf = Pdf::view('pdf.viaticos.informe-comision', ['viatico' => $viatico])
            ->format('A4');

        // Spatie PDF soporta save()
        Storage::put($path, $pdf->content());

        // Retornar URL firmada válida por 30 minutos
        return URL::temporarySignedRoute(
            'viaticos.informe.descargar',
            now()->addMinutes(30),
            ['archivo' => $nombreArchivo]
        );
    }
}
