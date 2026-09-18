<?php
namespace App\Services\Viatico;

use App\Models\Expediente\Servidor;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Support\Facades\Log;

class JefeFinancieroService
{


    /**
     * Retorna el user_id del jefe de Gestión Financiera
     * y el nombre de su cargo.
     *
     * @return array{user_id: int|null, cargo: string|null}
     */
    public function obtenerJefeFinanciero(): array
    {
        try {
            // La unidad viene de la bandera `es_unidad_financiera`; antes
            // estaba fija por número de registro, que cambia entre bases.
            $unidadId = UnidadAdministrativa::where('es_unidad_financiera', true)->value('id');

            $servidor = $unidadId
                ? Servidor::whereHas('puesto', fn ($q) => $q
                    ->where('es_jefe', true)
                    ->where('unidad_administrativa_id', $unidadId))
                    ->with(['puesto.cargo', 'user'])->first()
                : null;

            if (!$servidor || !$servidor->user) {
                Log::warning(
                    'JefeFinancieroService: no se encontró el jefe de la unidad ' .
                    'marcada como Gestión Financiera.'
                );
                return ['user_id' => null, 'cargo' => null];
            }

            $cargoNombre = $servidor->puesto?->cargo?->nombre
                ?? 'Director Financiero';

            return [
                'user_id' => $servidor->user->id,
                'cargo'   => $cargoNombre,
            ];
        } catch (\Throwable $e) {
            Log::error(
                'JefeFinancieroService error: ' . $e->getMessage()
            );
            return ['user_id' => null, 'cargo' => null];
        }
    }
}
