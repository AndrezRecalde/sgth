<?php

namespace App\Http\Requests\Estructura;

use App\Enums\RolPuesto;
use Illuminate\Validation\Validator;

/**
 * Lo que un puesto tiene que cumplir entre sus propios campos, al crearlo y al
 * editarlo. Las dos peticiones lo comparten; en la edición, lo que no viene se
 * toma de lo que el puesto ya tiene guardado.
 *
 * - Una jefatura tiene una sola plaza. Lo exige la base
 *   (`puesto_jefe_una_sola_plaza_check`), pero nada lo validaba antes: el
 *   formulario dejaba marcar «Es jefe de unidad» con dos plazas y la base
 *   respondía con un 500.
 * - El rol «Código del Trabajo» es el de los obreros, y solo cabe en un puesto
 *   de ese régimen; un puesto de ese régimen no lleva un rol de la LOSEP. Del
 *   rol sale, por ejemplo, si el puesto admite traspaso o revisión.
 */
final class CoherenciaDelPuesto
{
    /**
     * @param  array{es_jefe: mixed, plazas: mixed, regimen_laboral: mixed, rol_puesto: mixed}  $puesto
     */
    public static function validar(Validator $validator, array $puesto): void
    {
        $errores = $validator->errors();

        $esJefe = filter_var($puesto['es_jefe'], FILTER_VALIDATE_BOOLEAN);
        if ($esJefe && is_numeric($puesto['plazas']) && (int) $puesto['plazas'] !== 1 && ! $errores->has('plazas')) {
            $errores->add(
                'plazas',
                'Una jefatura tiene una sola plaza: la unidad tiene un solo jefe. Si hacen falta más personas, son otro puesto de la unidad.',
            );
        }

        $rol = $puesto['rol_puesto'] instanceof RolPuesto ? $puesto['rol_puesto']->value : $puesto['rol_puesto'];
        $regimen = $puesto['regimen_laboral'];

        if (blank($rol) || $errores->has('rol_puesto') || $errores->has('regimen_laboral')) {
            return;
        }

        if ($rol === RolPuesto::CODIGO_TRABAJO->value && $regimen !== 'codigo_trabajo') {
            $errores->add('rol_puesto', 'El rol «Código del Trabajo» es el de los obreros: el puesto es LOSEP.');
        } elseif ($rol !== RolPuesto::CODIGO_TRABAJO->value && $regimen === 'codigo_trabajo') {
            $errores->add('rol_puesto', 'Un puesto del Código del Trabajo lleva el rol «Código del Trabajo» o ninguno.');
        }
    }
}
