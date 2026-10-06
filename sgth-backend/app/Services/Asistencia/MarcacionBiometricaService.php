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

    /** La que el biométrico pone a quien no tiene la cédula cargada. */
    private const CEDULA_DE_RELLENO = '1111111111';

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
     * Devuelve false si la cédula no está en el biométrico.
     *
     * La hora va en ISO 8601 con «T» («2026-10-05T17:20:00»), que SQL Server
     * lee igual en cualquier idioma. Con «Y-m-d H:i:s» el servidor, que está
     * en español (DATEFORMAT dmy), cambiaba el mes por el día: el 5 de octubre
     * se guardaba como 10 de mayo, y del día 13 en adelante la inserción
     * fallaba por fecha fuera de rango.
     *
     * @throws ReglaNegocioException si la cédula no identifica a una sola
     *         persona (ver usuarioPorCedula).
     */
    public function registrarMarcacion(string $cedula, string $tipo, CarbonInterface $momento): bool
    {
        $userId = $this->usuarioPorCedula($cedula);

        if ($userId === null) {
            return false;
        }

        $this->conexion()->statement(
            'INSERT INTO CHECKINOUT
                (USERID, CHECKTIME, CHECKTYPE, SENSORID, MARCTYPE)
             VALUES (?, ?, ?, 4, ?)',
            [
                $userId,
                $momento->format('Y-m-d\TH:i:s'),
                $tipo,
                'IR',
            ]
        );

        return true;
    }

    /**
     * El USERID del biométrico al que corresponde la cédula, o null si no está.
     *
     * Las mismas reglas que sp_SGTH_MarcacionesPorCedula, para que la marcación
     * online caiga en el mismo registro del que luego se leen las marcaciones.
     * Antes se buscaba `SSN = ?` y se tomaba la primera fila:
     *
     * - Un SSN guardado sin el cero inicial (9 dígitos) no se encontraba.
     * - Una cédula repetida en varios registros caía en cualquiera de ellos:
     *   en el de un reingreso ya cerrado, o en el de otra persona cuyo SSN se
     *   copió mal.
     *
     * Ahora se compara a 10 dígitos. Entre varios registros se quedan los que
     * tienen un BADGENUMBER coherente con la cédula (sus últimos 9 dígitos), y
     * de esos el activo más reciente. Si ninguno es coherente y hay más de
     * uno, no hay forma de saber a quién marcar: error, como el procedimiento.
     *
     * @throws ReglaNegocioException si la cédula es inválida o la de relleno, o
     *         si está repartida en varios usuarios sin poder decidir.
     */
    public function usuarioPorCedula(string $cedula): ?int
    {
        $cedula = trim($cedula);

        if (!preg_match('/^\d{9,10}$/', $cedula)) {
            throw new ReglaNegocioException('La cédula debe tener 10 dígitos numéricos.');
        }

        $cedula = str_pad($cedula, 10, '0', STR_PAD_LEFT);

        if ($cedula === self::CEDULA_DE_RELLENO) {
            throw new ReglaNegocioException(
                '1111111111 es la cédula de relleno del biométrico, no identifica a nadie.'
            );
        }

        $candidatos = collect($this->conexion()->select(
            "SELECT USERID, BADGENUMBER, FechaRenuncia
             FROM USERINFO
             WHERE RIGHT('0000000000' + LTRIM(RTRIM(SSN)), 10) = ?",
            [$cedula]
        ));

        if ($candidatos->isEmpty()) {
            return null;
        }

        $coherentes = $candidatos->filter(
            fn (object $u) => substr(str_pad(trim((string) $u->BADGENUMBER), 9, '0', STR_PAD_LEFT), -9)
                === substr($cedula, -9)
        );

        if ($coherentes->isEmpty() && $candidatos->count() > 1) {
            throw new ReglaNegocioException(
                "La cédula {$cedula} está registrada en varios usuarios del biométrico y ninguno la tiene como código. Corríjase el SSN en USERINFO."
            );
        }

        // FechaRenuncia en 1900-01-01 (o vacía) es como el biométrico marca a
        // un activo.
        $activo = fn (object $u) => $u->FechaRenuncia === null
            || str_starts_with((string) $u->FechaRenuncia, '1900-01-01');

        return (int) ($coherentes->isEmpty() ? $candidatos : $coherentes)
            ->sortByDesc(fn (object $u) => [(int) $activo($u), (int) $u->USERID])
            ->first()
            ->USERID;
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
