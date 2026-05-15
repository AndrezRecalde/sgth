<?php

namespace App\Services\Expediente;

use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class CertificadoLaboralService
{
    public function generarCertificado(Servidor $servidor): string
    {
        $servidor->load(['contratos' => function ($query) {
            $query->orderBy('fecha_inicio', 'asc');
        }, 'contratos.unidadAdministrativa', 'contratos.puesto']);

        $timestamp = now()->timestamp;
        $nombreArchivo = "certificado_{$servidor->cedula}_{$timestamp}.pdf";
        $rutaRelativa = "certificados-laborales/{$nombreArchivo}";

        // Asegurarse que el directorio existe
        Storage::disk('local')->makeDirectory('certificados-laborales');

        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        Pdf::view('pdf.expediente.certificado-laboral', ['servidor' => $servidor])
            ->save($rutaAbsoluta);

        return $rutaRelativa;
    }
}
