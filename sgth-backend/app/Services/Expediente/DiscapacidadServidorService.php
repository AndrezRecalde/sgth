<?php

namespace App\Services\Expediente;

use App\Models\Expediente\DiscapacidadServidor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class DiscapacidadServidorService
{
    public function listar(int $servidorId)
    {
        return DiscapacidadServidor::where('servidor_id', $servidorId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function crear(int $servidorId, array $data)
    {
        $data['servidor_id'] = $servidorId;

        if (isset($data['archivo_carnet']) && $data['archivo_carnet'] instanceof UploadedFile) {
            $data['carnet_nombre_archivo'] = $data['archivo_carnet']->getClientOriginalName();
            $data['carnet_ruta'] = $data['archivo_carnet']->store('expediente/discapacidades', 'local');
            unset($data['archivo_carnet']);
        }

        return DiscapacidadServidor::create($data);
    }

    public function actualizar(DiscapacidadServidor $discapacidad, array $data)
    {
        if (isset($data['archivo_carnet']) && $data['archivo_carnet'] instanceof UploadedFile) {
            if ($discapacidad->carnet_ruta) {
                Storage::disk('local')->delete($discapacidad->carnet_ruta);
            }
            $data['carnet_nombre_archivo'] = $data['archivo_carnet']->getClientOriginalName();
            $data['carnet_ruta'] = $data['archivo_carnet']->store('expediente/discapacidades', 'local');
            unset($data['archivo_carnet']);
        }

        $discapacidad->update($data);

        return $discapacidad;
    }

    public function eliminar(DiscapacidadServidor $discapacidad)
    {
        if ($discapacidad->carnet_ruta) {
            Storage::disk('local')->delete($discapacidad->carnet_ruta);
        }
        
        $discapacidad->delete();
    }
}
