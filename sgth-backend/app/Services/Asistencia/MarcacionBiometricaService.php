<?php

namespace App\Services\Asistencia;

use App\Exceptions\ReglaNegocioException;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Marcaciones del biométrico (Sirha7, SQL Server), buscadas por cédula.
 *
 * Usa `sp_SGTH_MarcacionesPorCedula` (database/sqlsrv/), que encuentra al
 * servidor por `USERINFO.SSN`. Antes se llamaba a
 * `sp_GetMarcacionesPorDiaYTipo_v3`, que busca por BADGENUMBER: ese código
 * son los últimos 9 dígitos de la cédula, así que al pasarle la cédula de 10
 * dígitos no encontraba a nadie de Esmeraldas (08…). El v3 sigue en la base
 * porque lo usa otro sistema; aquí ya no se llama.
 *
 * Solo lectura: el procedimiento no escribe en ninguna tabla.
 */
class MarcacionBiometricaService
{
    /** Número con que SQL Server marca los RAISERROR del procedimiento. */
    private const ERROR_DEL_PROCEDIMIENTO = 50000;

    /**
     * Una fila por día con horario, marcaciones o permiso, en orden de fecha.
     *
     * @return list<object>
     *
     * @throws ReglaNegocioException si el procedimiento rechaza la consulta:
     *         cédula inválida o de relleno, rango de más de un año, o una
     *         cédula repartida en varios usuarios del biométrico.
     * @throws QueryException si no se puede consultar el biométrico.
     */
    public function porCedula(string $cedula, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        try {
            // Fechas en ISO básico: el servidor está en español (DATEFORMAT dmy).
            return $this->conexion()->select(
                'EXEC dbo.sp_SGTH_MarcacionesPorCedula ?, ?, ?',
                [$cedula, $desde->format('Ymd'), $hasta->format('Ymd')]
            );
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === self::ERROR_DEL_PROCEDIMIENTO) {
                throw new ReglaNegocioException(self::mensajeDelProcedimiento($e));
            }

            throw $e;
        }
    }

    /** Aparte para que las pruebas puedan darle una conexión simulada. */
    protected function conexion(): ConnectionInterface
    {
        return DB::connection('sqlsrv');
    }

    /**
     * El texto del RAISERROR, sin los prefijos del driver
     * («[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]»).
     */
    private static function mensajeDelProcedimiento(QueryException $e): string
    {
        return trim(preg_replace('/^(\[[^\]]*\])+/', '', (string) ($e->errorInfo[2] ?? '')))
            ?: 'El biométrico rechazó la consulta.';
    }
}
