<?php

namespace App\Services\Asistencia;

use App\Exceptions\ReglaNegocioException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cómo habla el SGTH con Sirha7, el biométrico (SQL Server).
 *
 * Entra con `sgth_app`, que solo puede ejecutar los procedimientos de
 * database/sqlsrv/: no lee ni escribe ninguna tabla. Lo comparten las
 * marcaciones y los permisos para que un RAISERROR se lea igual en los dos.
 */
trait ProcedimientosSirha7
{
    /**
     * Ejecuta un procedimiento y convierte su RAISERROR (número 50000) en una
     * regla de negocio con el mensaje tal cual. Los demás errores siguen su
     * camino: que el biométrico no responda no es culpa de quien pidió.
     *
     * @return list<object>
     */
    private function ejecutar(string $sql, array $parametros): array
    {
        try {
            return $this->conexion()->select($sql, $parametros);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 50000) {
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
            ?: 'El biométrico rechazó la operación.';
    }
}
