<?php

namespace App\Services\Asistencia;

use App\Exceptions\ReglaNegocioException;
use Illuminate\Database\QueryException;

/**
 * Los permisos que el SGTH escribe en Sirha7 (dbo.USER_SPEDAY).
 *
 * Solo llama a los procedimientos de database/sqlsrv/ —sp_SGTH_TiposPermiso,
 * sp_SGTH_RegistrarPermiso y sp_SGTH_RetirarPermiso—, creados en Sirha7 el
 * 2026-10-08. Las reglas de cómo se escribe cada día (horario, fines de
 * semana, cruces, registro coherente) viven en el procedimiento, no aquí: es
 * el único que ve las tablas.
 */
class Sirha7PermisoService
{
    use ProcedimientosSirha7;

    /**
     * Los tipos de dbo.LeaveClass, por nombre.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function tipos(): array
    {
        return array_map(
            fn (object $t) => ['id' => (int) $t->LeaveId, 'nombre' => (string) $t->LeaveName],
            $this->ejecutar('EXEC dbo.sp_SGTH_TiposPermiso', [])
        );
    }

    /**
     * Registra el permiso: una fila por día entre `$desde` y `$hasta`.
     *
     * Con `$horaInicio` y `$horaFin` es un permiso por horas de un solo día y
     * se escribe tal cual; sin ellas, cada día es de jornada completa.
     *
     * Las fechas viajan como `Y-m-d` a parámetros DATE, que SQL Server lee igual
     * en cualquier idioma (el servidor está en español: un DATETIME como texto
     * se leería día/mes/año).
     *
     * @return array{userid: int, filas: list<array{id: int, inicio: string, fin: string}>, omitidos: list<array{dia: string, motivo: string}>, ya_registrado: bool}
     *
     * @throws ReglaNegocioException si el procedimiento no escribe nada: cédula
     *         sin registro coherente, cruce en un permiso de un día, ningún día
     *         con jornada…
     * @throws QueryException si no se puede llegar al biométrico.
     */
    public function registrar(
        string $cedula,
        int $leaveId,
        string $desde,
        string $hasta,
        ?string $horaInicio,
        ?string $horaFin,
        string $referencia,
    ): array {
        $resultado = $this->ejecutar(
            'EXEC dbo.sp_SGTH_RegistrarPermiso @Cedula = ?, @LeaveId = ?, @Desde = ?, @Hasta = ?, @HoraInicio = ?, @HoraFin = ?, @Referencia = ?',
            [$cedula, $leaveId, $desde, $hasta, $horaInicio, $horaFin, $referencia]
        );

        $filas = [];
        $omitidos = [];
        $userId = null;
        $yaRegistrado = false;

        foreach ($resultado as $r) {
            $userId ??= (int) $r->USERID;

            if ($r->Resultado === 'omitida') {
                $omitidos[] = ['dia' => substr((string) $r->Inicio, 0, 10), 'motivo' => (string) $r->Detalle];
                continue;
            }

            $yaRegistrado = $yaRegistrado || $r->Resultado === 'ya_registrado';
            $filas[] = [
                'id'     => (int) $r->ID,
                'inicio' => substr((string) $r->Inicio, 0, 19),
                'fin'    => substr((string) $r->Fin, 0, 19),
            ];
        }

        if ($userId === null || $filas === []) {
            // El procedimiento siempre devuelve al menos una fila escrita o
            // lanza un error: llegar aquí es que respondió algo inesperado.
            throw new ReglaNegocioException('Sirha7 no confirmó el registro del permiso.');
        }

        return [
            'userid'        => $userId,
            'filas'         => $filas,
            'omitidos'      => $omitidos,
            'ya_registrado' => $yaRegistrado,
        ];
    }
}
