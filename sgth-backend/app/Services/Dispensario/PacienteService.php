<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\PacienteServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\Beneficiario;
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

        $beneficiario = $this->buscarBeneficiario($cedula);
        if ($beneficiario) {
            return $beneficiario;
        }

        throw new ReglaNegocioException(
            'No se encontró ningún servidor o familiar ' .
            'registrado con esa cédula.',
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

    private function buscarBeneficiario(string $cedula): ?array
    {
        $beneficiario = Beneficiario::where('cedula', $cedula)
            ->activos()
            ->with('servidor')
            ->first();

        if (!$beneficiario) {
            return null;
        }

        $historia = HistoriaClinica::where(
            'beneficiario_id', $beneficiario->id
        )->first();

        return [
            'tipo'                   => 'beneficiario',
            'id'                     => $beneficiario->id,
            'cedula'                 => $beneficiario->cedula,
            'nombre_completo'        => trim(
                "{$beneficiario->nombre} {$beneficiario->apellido}"
            ),
            'tipo_familiar'          => $beneficiario->tipo_familiar,
            'servidor_titular'       => $beneficiario->servidor
                ? trim(
                    "{$beneficiario->servidor->nombre} " .
                    "{$beneficiario->servidor->apellido}"
                ) : null,
            'tiene_historia_clinica' => (bool) $historia,
            'historia_clinica_id'    => $historia?->id,
        ];
    }
}
