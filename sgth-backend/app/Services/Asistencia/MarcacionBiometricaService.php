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
 * La consulta es de solo lectura: el procedimiento no escribe en ninguna
 * tabla. La única escritura es la marcación online (registrarMarcacion).
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

    /**
     * Registra una marcación online en CHECKINOUT a nombre de la cédula.
     *
     * Devuelve false si la cédula no está en USERINFO.SSN.
     *
     * La hora va en ISO 8601 con «T» («2026-10-05T17:20:00»), que SQL Server
     * lee igual en cualquier idioma. Con «Y-m-d H:i:s» el servidor, que está
     * en español (DATEFORMAT dmy), cambiaba el mes por el día: el 5 de octubre
     * se guardaba como 10 de mayo, y del día 13 en adelante la inserción
     * fallaba por fecha fuera de rango.
     */
    public function registrarMarcacion(string $cedula, string $tipo, CarbonInterface $momento): bool
    {
        $usuario = $this->conexion()->select(
            'SELECT USERID FROM USERINFO WHERE SSN = ?',
            [$cedula]
        );

        if (empty($usuario)) {
            return false;
        }

        $this->conexion()->statement(
            'INSERT INTO CHECKINOUT
                (USERID, CHECKTIME, CHECKTYPE, SENSORID, MARCTYPE)
             VALUES (?, ?, ?, 4, ?)',
            [
                $usuario[0]->USERID,
                $momento->format('Y-m-d\TH:i:s'),
                $tipo,
                'IR',
            ]
        );

        return true;
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
