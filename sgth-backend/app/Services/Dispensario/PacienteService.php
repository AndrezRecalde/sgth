<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\PacienteServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;

final class PacienteService implements PacienteServiceInterface
{
    public function buscarPorCedula(string $cedula): array
    {
        $servidor = $this->buscarServidor($cedula);
        if ($servidor) {
            return $servidor;
        }

        $cargaFamiliar = $this->buscarCargaFamiliar($cedula);
        if ($cargaFamiliar) {
            return $cargaFamiliar;
        }

        throw new ReglaNegocioException(
            'No se encontró ningún servidor o familiar ' .
            'registrado con esa cédula. Si es un familiar, ' .
            'debe estar registrado como carga familiar con ' .
            'su número de cédula en el Expediente del servidor.',
            404
        );
    }

    private function buscarServidor(string $cedula): ?array
    {
        $servidor = Servidor::porCedula($cedula)
            ->with(['puesto.cargo', 'unidadAdministrativa'])
            ->first();

        if (!$servidor) {
            return null;
        }

        $historia = HistoriaClinica::where(
            'servidor_id', $servidor->id
        )->first();

        return [
            'tipo'                   => 'servidor',
            'id'                     => $servidor->id,
            'cedula'                 => $servidor->cedula,
            'nombre_completo'        => trim(
                "{$servidor->nombre} {$servidor->apellido}"
            ),
            'puesto'                 => $servidor->puesto
                ?->cargo?->nombre,
            'unidad_administrativa'  => $servidor
                ->unidadAdministrativa?->nombre,
            'tiene_historia_clinica' => (bool) $historia,
            'historia_clinica_id'    => $historia?->id,
        ];
    }

    private function buscarCargaFamiliar(string $cedula): ?array
    {
        $cargaFamiliar = CargaFamiliar::where('cedula', $cedula)
            ->activos()
            ->with('servidor')
            ->first();

        if (!$cargaFamiliar) {
            return null;
        }

        $historia = HistoriaClinica::where(
            'carga_familiar_id', $cargaFamiliar->id
        )->first();

        return [
            'tipo'                   => 'beneficiario',
            'id'                     => $cargaFamiliar->id,
            'cedula'                 => $cargaFamiliar->cedula,
            'nombre_completo'        => trim(
                "{$cargaFamiliar->nombres} {$cargaFamiliar->apellidos}"
            ),
            'tipo_familiar'          => $cargaFamiliar->parentesco?->value
                ?? (string) $cargaFamiliar->parentesco,
            'servidor_titular'       => $cargaFamiliar->servidor
                ? trim(
                    "{$cargaFamiliar->servidor->nombre} " .
                    "{$cargaFamiliar->servidor->apellido}"
                ) : null,
            'tiene_historia_clinica' => (bool) $historia,
            'historia_clinica_id'    => $historia?->id,
        ];
    }
}
