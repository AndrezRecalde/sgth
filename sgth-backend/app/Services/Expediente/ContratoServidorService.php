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
            ->with(['unidadAdministrativa', 'puesto.cargo'])
            ->orderBy('fecha_inicio', 'desc')
            ->get();
    }

    public function crear(int $servidorId, array $data)
    {
        $data['servidor_id'] = $servidorId;

        if (isset($data['archivo_contrato'])
            && $data['archivo_contrato'] instanceof UploadedFile) {
            $data['documento_ruta'] = $data['archivo_contrato']
                ->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        $contrato = ContratoServidor::create($data);

        // Sincronizar régimen laboral en el servidor
        $this->sincronizarRegimenServidor($servidorId, $data);

        return $contrato->load(['puesto.cargo', 'unidadAdministrativa']);
    }

    public function actualizar(ContratoServidor $contrato, array $data)
    {
        if (isset($data['archivo_contrato'])
            && $data['archivo_contrato'] instanceof UploadedFile) {
            if ($contrato->documento_ruta) {
                Storage::disk('local')->delete($contrato->documento_ruta);
            }
            $data['documento_ruta'] = $data['archivo_contrato']
                ->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        $contrato->update($data);

        // Sincronizar régimen laboral si el contrato es vigente
        if (($data['estado'] ?? $contrato->estado) === 'vigente') {
            $this->sincronizarRegimenServidor(
                $contrato->servidor_id, $data
            );
        }

        return $contrato->fresh(['puesto.cargo', 'unidadAdministrativa']);
    }

    public function eliminar(ContratoServidor $contrato)
    {
        if ($contrato->documento_ruta) {
            Storage::disk('local')->delete($contrato->documento_ruta);
        }
        
        $contrato->delete();
    }

    private function sincronizarRegimenServidor(
        int $servidorId,
        array $data
    ): void {
        $tipoNombramiento = $data['tipo_nombramiento'] ?? null;
        if (!$tipoNombramiento) return;

        $tipoNombramientoVal = $tipoNombramiento instanceof \UnitEnum
            ? $tipoNombramiento->value
            : $tipoNombramiento;

        $regimen = match($tipoNombramientoVal) {
            'codigo_trabajo',
            'servicios_profesionales' => 'codigo_trabajo',
            default                   => 'losep',
        };

        $update = ['regimen_laboral' => $regimen];

        // Sincronizar puesto y unidad si vienen en el contrato
        if (!empty($data['puesto_id'])) {
            $update['puesto_id'] = $data['puesto_id'];
        }
        if (!empty($data['unidad_administrativa_id'])) {
            $update['unidad_administrativa_id'] =
                $data['unidad_administrativa_id'];
        }

        \App\Models\Expediente\Servidor::where('id', $servidorId)
            ->update($update);
    }
}
