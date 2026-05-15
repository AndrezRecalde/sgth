<?php

namespace App\Services\Expediente;

use App\Models\Expediente\EnfermedadCatastroficaServidor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class EnfermedadCatastroficaServidorService
{
    public function listar(int $servidorId)
    {
        return EnfermedadCatastroficaServidor::where('servidor_id', $servidorId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function crear(int $servidorId, array $data)
    {
        $data['servidor_id'] = $servidorId;

        if (isset($data['archivo_certificado']) && $data['archivo_certificado'] instanceof UploadedFile) {
            $data['certificado_nombre_archivo'] = $data['archivo_certificado']->getClientOriginalName();
            $data['certificado_ruta'] = $data['archivo_certificado']->store('expediente/enfermedades', 'local');
            unset($data['archivo_certificado']);
        }

        return EnfermedadCatastroficaServidor::create($data);
    }

    public function actualizar(EnfermedadCatastroficaServidor $enfermedad, array $data)
    {
        if (isset($data['archivo_certificado']) && $data['archivo_certificado'] instanceof UploadedFile) {
            if ($enfermedad->certificado_ruta) {
                Storage::disk('local')->delete($enfermedad->certificado_ruta);
            }
            $data['certificado_nombre_archivo'] = $data['archivo_certificado']->getClientOriginalName();
            $data['certificado_ruta'] = $data['archivo_certificado']->store('expediente/enfermedades', 'local');
            unset($data['archivo_certificado']);
        }

        $enfermedad->update($data);

        return $enfermedad;
    }

    public function eliminar(EnfermedadCatastroficaServidor $enfermedad)
    {
        if ($enfermedad->certificado_ruta) {
            Storage::disk('local')->delete($enfermedad->certificado_ruta);
        }
        
        $enfermedad->delete();
    }
}
