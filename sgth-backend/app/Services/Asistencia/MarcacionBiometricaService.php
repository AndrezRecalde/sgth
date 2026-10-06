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
 * El SGTH entra con el usuario `sgth_app`, que solo puede ejecutar dos
 * procedimientos (database/sqlsrv/): no lee ni escribe ninguna tabla. Antes
 * entraba con `sa`, que administra el servidor entero.
 *
 * - `sp_SGTH_MarcacionesPorCedula`: la consulta, por `USERINFO.SSN`. Antes se
 *   llamaba a `sp_GetMarcacionesPorDiaYTipo_v3`, que busca por BADGENUMBER:
 *   ese código son los últimos 9 dígitos de la cédula, así que al pasarle la
 *   cédula de 10 dígitos no encontraba a nadie de Esmeraldas (08…). El v3
 *   sigue en la base porque lo usa otro sistema; aquí ya no se llama.
 * - `sp_SGTH_RegistrarMarcacionOnline`: la marcación online.
 *
 * Los dos buscan a la persona con la misma función
 * (`fn_SGTH_UsuariosPorCedula`), así que la marcación online cae en el mismo
 * registro del que luego se leen las marcaciones.
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
        // Fechas en ISO básico: el servidor está en español (DATEFORMAT dmy).
        return $this->ejecutar(
            'EXEC dbo.sp_SGTH_MarcacionesPorCedula ?, ?, ?',
            [$cedula, $desde->format('Ymd'), $hasta->format('Ymd')]
        );
    }

    /**
     * Registra una marcación online a nombre de la cédula.
     *
     * Devuelve false si la cédula no está en el biométrico. Una marcación
     * repetida en el mismo segundo (doble toque) cuenta como registrada: el
     * procedimiento no inserta otra.
     *
     * La ubicación va a GEOLT/GEOLG, las dos o ninguna. Antes se validaba en
     * la petición y se descartaba.
     *
     * La hora va en ISO 8601 con «T», que SQL Server lee igual en cualquier
     * idioma, y el parámetro del procedimiento es DATETIME. Con «Y-m-d H:i:s»
     * el servidor, en español, cambiaba el mes por el día: el 5 de octubre se
     * guardaba como 10 de mayo.
     *
     * @throws ReglaNegocioException si el procedimiento la rechaza: cédula
     *         inválida, de relleno o repartida en varios usuarios, hora a más
     *         de 15 minutos de la del biométrico, ubicación incompleta.
     */
    public function registrarMarcacion(
        string $cedula,
        string $tipo,
        CarbonInterface $momento,
        ?float $latitud = null,
        ?float $longitud = null,
    ): bool {
        $resultado = $this->ejecutar(
            'EXEC dbo.sp_SGTH_RegistrarMarcacionOnline ?, ?, ?, ?, ?, ?',
            [
                $cedula,
                $tipo,
                $momento->format('Y-m-d\TH:i:s'),
                $latitud,
                $longitud,
                (string) config('services.biometrico.sensor_online'),
            ]
        );

        return ($resultado[0]->Resultado ?? null) !== 'no_encontrada';
    }

    /**
     * Ejecuta un procedimiento y convierte su RAISERROR en una regla de
     * negocio con el mensaje tal cual. Los demás errores siguen su camino.
     *
     * @return list<object>
     */
    private function ejecutar(string $sql, array $parametros): array
    {
        try {
            return $this->conexion()->select($sql, $parametros);
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
