<?php
namespace App\Services\Viatico;

use App\Models\Expediente\Servidor;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Support\Facades\Log;

class JefeFinancieroService
{
    // ID de la unidad Gestión Financiera
    // Configurable desde .env o config
    private const UNIDAD_FINANCIERA_ID = 32;

    /**
     * Retorna el user_id del jefe de Gestión Financiera
     * y el nombre de su cargo.
     *
     * @return array{user_id: int|null, cargo: string|null}
     */
    public function obtenerJefeFinanciero(): array
    {
        try {
            $servidor = Servidor::whereHas('puesto', function ($q) {
                $q->where('es_jefe', true)
                  ->where(
                      'unidad_administrativa_id',
                      self::UNIDAD_FINANCIERA_ID
                  );
            })->with(['puesto.cargo', 'user'])->first();

            if (!$servidor || !$servidor->user) {
                Log::warning(
                    'JefeFinancieroService: No se encontró ' .
                    'el jefe de Gestión Financiera (unidad 32).'
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
