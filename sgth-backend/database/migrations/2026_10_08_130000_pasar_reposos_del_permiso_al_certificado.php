<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los certificados médicos ya emitidos pasan al flujo nuevo (decisión del
 * 2026-10-08): hasta ahora cada uno creaba un permiso de tipo enfermedad, y
 * era el permiso lo que se aprobaba y se registraba en Sirha7.
 *
 * Por cada certificado con permiso:
 * - Si el permiso se aprobó en Sirha7, la aprobación y sus filas pasan al
 *   certificado tal cual, con la referencia con que se registró
 *   («SGTH PER-…»): con ella se retira si se anula. En Sirha7 no se toca nada.
 * - Si Trabajo Social lo validó sin Sirha7 (antes de la fecha de corte), el
 *   certificado queda aprobado a mano, con una nota que lo dice.
 * - El permiso se borra en blando: deja de verse en Asistencia y en el
 *   portal, donde el reposo pasa a mostrarse como certificado. La fila queda.
 *
 * `down()` lo devuelve: restaura los permisos de los certificados y les
 * devuelve sus filas de Sirha7. Supone que ningún permiso de certificado
 * estaba ya borrado antes de esta migración; si alguno lo estaba, lo restaura
 * también.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $filas = DB::table('certificados_medicos as c')
                ->join('permisos_servidor as p', 'p.id', '=', 'c.permiso_servidor_id')
                ->whereNull('p.deleted_at')
                ->whereNull('c.deleted_at')
                ->orderBy('c.id')
                ->get([
                    'c.id', 'c.aprobado_en', 'p.id as permiso_id', 'p.folio as permiso_folio', 'p.estado',
                    'p.sirha7_aprobado_en', 'p.sirha7_aprobado_por', 'p.sirha7_leave_id', 'p.sirha7_leave_nombre',
                    'p.sirha7_userid', 'p.sirha7_dias_omitidos', 'p.validado_ts_por', 'p.validado_ts_en',
                ]);

            foreach ($filas as $f) {
                if ($f->aprobado_en === null && $f->sirha7_aprobado_en !== null) {
                    DB::table('certificados_medicos')->where('id', $f->id)->update([
                        'aprobado_por'         => $f->sirha7_aprobado_por,
                        'aprobado_en'          => $f->sirha7_aprobado_en,
                        'registro_sirha7'      => 'sgth',
                        'sirha7_leave_id'      => $f->sirha7_leave_id,
                        'sirha7_leave_nombre'  => $f->sirha7_leave_nombre,
                        'sirha7_userid'        => $f->sirha7_userid,
                        'sirha7_referencia'    => 'SGTH ' . $f->permiso_folio,
                        'sirha7_dias_omitidos' => $f->sirha7_dias_omitidos,
                    ]);

                    DB::statement(<<<'SQL'
                        INSERT INTO certificado_sirha7_filas (certificado_medico_id, sirha7_id, inicio, fin, created_at, updated_at)
                        SELECT ?, sirha7_id, inicio, fin, created_at, NOW()
                        FROM permiso_sirha7_filas
                        WHERE permiso_servidor_id = ?
                    SQL, [$f->id, $f->permiso_id]);

                    DB::table('permiso_sirha7_filas')->where('permiso_servidor_id', $f->permiso_id)->delete();
                } elseif ($f->aprobado_en === null && $f->estado === 'validado_trabajo_social' && $f->validado_ts_en !== null) {
                    DB::table('certificados_medicos')->where('id', $f->id)->update([
                        'aprobado_por'    => $f->validado_ts_por,
                        'aprobado_en'     => $f->validado_ts_en,
                        'registro_sirha7' => 'manual',
                        'nota_aprobacion' => "Validado por Trabajo Social como el permiso {$f->permiso_folio}, " .
                            'antes de que los reposos se aprobaran como certificados; en Sirha7 se cargó a mano.',
                    ]);
                }

                DB::table('permisos_servidor')->where('id', $f->permiso_id)->update(['deleted_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $filas = DB::table('certificados_medicos as c')
                ->join('permisos_servidor as p', 'p.id', '=', 'c.permiso_servidor_id')
                ->whereNotNull('p.deleted_at')
                ->get(['c.id', 'c.registro_sirha7', 'c.aprobado_en', 'p.id as permiso_id', 'p.sirha7_aprobado_en', 'p.validado_ts_en']);

            foreach ($filas as $f) {
                DB::table('permisos_servidor')->where('id', $f->permiso_id)->update(['deleted_at' => null]);

                // Solo lo que esta migración copió: la aprobación que coincide con la del permiso.
                $copiadaDeSirha7 = $f->registro_sirha7 === 'sgth' && $f->aprobado_en == $f->sirha7_aprobado_en;
                $copiadaDeTs = $f->registro_sirha7 === 'manual' && $f->aprobado_en == $f->validado_ts_en;

                if ($copiadaDeSirha7) {
                    DB::statement(<<<'SQL'
                        INSERT INTO permiso_sirha7_filas (permiso_servidor_id, sirha7_id, inicio, fin, created_at, updated_at)
                        SELECT ?, sirha7_id, inicio, fin, created_at, NOW()
                        FROM certificado_sirha7_filas
                        WHERE certificado_medico_id = ?
                    SQL, [$f->permiso_id, $f->id]);

                    DB::table('certificado_sirha7_filas')->where('certificado_medico_id', $f->id)->delete();
                }

                if ($copiadaDeSirha7 || $copiadaDeTs) {
                    DB::table('certificados_medicos')->where('id', $f->id)->update([
                        'aprobado_por' => null, 'aprobado_en' => null, 'registro_sirha7' => null,
                        'nota_aprobacion' => null, 'sirha7_leave_id' => null, 'sirha7_leave_nombre' => null,
                        'sirha7_userid' => null, 'sirha7_referencia' => null, 'sirha7_dias_omitidos' => null,
                    ]);
                }
            }
        });
    }
};
