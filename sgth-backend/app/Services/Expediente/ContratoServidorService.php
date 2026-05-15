<?php

namespace App\Services\Expediente;

use App\Models\Expediente\ContratoServidor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ContratoServidorService
{
    public function listar(int $servidorId)
    {
        return ContratoServidor::where('servidor_id', $servidorId)
            ->with(['unidadAdministrativa', 'puesto'])
            ->orderBy('fecha_inicio', 'desc')
            ->get();
    }

    public function crear(int $servidorId, array $data)
    {
        $data['servidor_id'] = $servidorId;

        if (isset($data['archivo_contrato']) && $data['archivo_contrato'] instanceof UploadedFile) {
            $data['documento_ruta'] = $data['archivo_contrato']->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        return ContratoServidor::create($data);
    }

    public function actualizar(ContratoServidor $contrato, array $data)
    {
        if (isset($data['archivo_contrato']) && $data['archivo_contrato'] instanceof UploadedFile) {
            if ($contrato->documento_ruta) {
                Storage::disk('local')->delete($contrato->documento_ruta);
            }
            $data['documento_ruta'] = $data['archivo_contrato']->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        $contrato->update($data);

        return $contrato;
    }

    public function eliminar(ContratoServidor $contrato)
    {
        if ($contrato->documento_ruta) {
            Storage::disk('local')->delete($contrato->documento_ruta);
        }
        
        $contrato->delete();
    }
}
