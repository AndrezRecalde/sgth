<?php

namespace App\Services\Expediente;

use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
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

        $estadoNuevo = $data['estado'] ?? 'vigente';
        $estadoNuevoVal = $estadoNuevo instanceof \App\Enums\EstadoContrato ? $estadoNuevo->value : (string)$estadoNuevo;
        if ($estadoNuevoVal === 'vigente' && !empty($data['puesto_id']) && !empty($data['tipo_nombramiento'])) {
            $this->validarVacante((int) $data['puesto_id'], $this->valorTipoNombramiento($data['tipo_nombramiento']));
        }

        if (isset($data['archivo_contrato'])
            && $data['archivo_contrato'] instanceof UploadedFile) {
            $data['documento_ruta'] = $data['archivo_contrato']
                ->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        $contrato = ContratoServidor::create($data);

        // Sincronizar régimen laboral en el servidor si es vigente
        $estado = $data['estado'] ?? $contrato->estado;
        $estadoVal = $estado instanceof \App\Enums\EstadoContrato ? $estado->value : (string)$estado;
        if ($estadoVal === 'vigente') {
            $this->sincronizarRegimenServidor($servidorId, $data, $contrato);
        }

        return $contrato->load(['puesto.cargo', 'unidadAdministrativa']);
    }

    public function actualizar(ContratoServidor $contrato, array $data)
    {
        $estadoNuevo = $data['estado'] ?? $contrato->estado;
        $estadoNuevoVal = $estadoNuevo instanceof \App\Enums\EstadoContrato ? $estadoNuevo->value : (string)$estadoNuevo;
        $puestoId = $data['puesto_id'] ?? $contrato->puesto_id;
        $tipoNombramiento = $data['tipo_nombramiento'] ?? $contrato->tipo_nombramiento;
        if ($estadoNuevoVal === 'vigente' && $puestoId && $tipoNombramiento) {
            $this->validarVacante(
                (int) $puestoId,
                $this->valorTipoNombramiento($tipoNombramiento),
                $contrato->id
            );
        }

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
        $estado = $data['estado'] ?? $contrato->estado;
        $estadoVal = $estado instanceof \App\Enums\EstadoContrato ? $estado->value : (string)$estado;
        if ($estadoVal === 'vigente') {
            $this->sincronizarRegimenServidor(
                $contrato->servidor_id,
                array_merge($contrato->toArray(), $data),
                $contrato
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

    private function valorTipoNombramiento(mixed $tipoNombramiento): string
    {
        return $tipoNombramiento instanceof \UnitEnum
            ? $tipoNombramiento->value
            : (string) $tipoNombramiento;
    }

    /**
     * Impide asignar un servidor a un puesto sin plazas disponibles.
     * Servicios Profesionales no consume plaza (contrato civil), por lo que
     * queda excluido tanto de esta validación como del conteo de ocupadas.
     */
    private function validarVacante(
        int $puestoId,
        string $tipoNombramiento,
        ?int $exceptoContratoId = null
    ): void {
        if ($tipoNombramiento === TipoNombramiento::SERVICIOS_PROFESIONALES->value) {
            return;
        }

        $puesto = Puesto::findOrFail($puestoId);

        $ocupadas = $puesto->contratosVigentes()
            ->where('tipo_nombramiento', '!=', TipoNombramiento::SERVICIOS_PROFESIONALES->value)
            ->when($exceptoContratoId, fn ($q) => $q->where('id', '!=', $exceptoContratoId))
            ->count();

        if ($ocupadas >= $puesto->plazas) {
            throw new ReglaNegocioException(
                'El puesto seleccionado no tiene plazas disponibles.'
            );
        }
    }

    private function sincronizarRegimenServidor(
        int $servidorId,
        array $data,
        ?ContratoServidor $contrato = null
    ): void {
        $tipoNombramiento = $data['tipo_nombramiento'] ?? null;
        if (!$tipoNombramiento) return;

        $tipoNombramientoVal = $tipoNombramiento instanceof \UnitEnum
            ? $tipoNombramiento->value
            : (string)$tipoNombramiento;

        $regimen = match($tipoNombramientoVal) {
            'codigo_trabajo',
            'servicios_profesionales' => 'codigo_trabajo',
            default                   => 'losep',
        };

        $update = [
            'regimen_laboral'   => $regimen,
            'tipo_nombramiento' => $tipoNombramientoVal,
        ];

        // Sync puesto y unidad
        if (!empty($data['puesto_id'])) {
            $update['puesto_id'] = $data['puesto_id'];
        }
        if (!empty($data['unidad_administrativa_id'])) {
            $update['unidad_administrativa_id'] =
                $data['unidad_administrativa_id'];
        }

        // Fecha de ingreso al GAD = fecha_inicio del contrato vigente actual.
        if (!empty($data['fecha_inicio'])) {
            $update['fecha_ingreso_institucion'] = $data['fecha_inicio'];
        }

        // Fecha de nombramiento oficial solo aplica a Nombramiento Permanente.
        $update['fecha_nombramiento'] =
            $tipoNombramientoVal === \App\Enums\TipoNombramiento::PERMANENTE->value
                ? ($data['fecha_inicio'] ?? null)
                : null;

        \App\Models\Expediente\Servidor::where('id', $servidorId)
            ->update($update);
    }
}
