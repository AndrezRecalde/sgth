<?php

namespace App\Services\Estructura;

use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;

/**
 * Lo que no se hace a mano con un puesto ocupado (diseño de Acciones de
 * Personal, 8.4 y 9.7; fase 7.1a). Hasta aquí Estructura dejaba cambiarle la
 * unidad, el régimen o las plazas, desactivarlo o borrarlo, y el ocupante se
 * quedaba con un contrato en una unidad que ya no era la del puesto, o sin
 * puesto: la nómina lo saltaba y sus documentos salían en blanco.
 *
 * - Ocupado: tiene un contrato vigente, de cualquier modalidad, o lo reserva
 *   un ascendido en prueba, o lo espera una acción registrada que todavía no
 *   rige. Ver `Puesto::scopeConOcupacion()`.
 * - Con un ocupante, la unidad cambia solo por el traspaso de puesto (LOSEP
 *   Art. 37), y el grado y la denominación, por la revisión de la
 *   clasificación (LOSEP Arts. 61 y 62; fase 7.1b), que emiten el acto de cada
 *   uno; el régimen no cambia, no se desactiva y las plazas no bajan de las
 *   ocupadas.
 * - El cargo con puestos ocupados se renombra solo para corregir cómo está
 *   escrito, con el motivo: un cambio de denominación es una reclasificación.
 * - Con historia —un contrato o una acción que lo nombran— no se borra: se
 *   desactiva [TH N16, la supresión queda aplazada].
 *
 * Va en el servicio y no en el modelo: el motor de efectos y las pruebas sí
 * cambian puestos ocupados, y deben poder hacerlo.
 */
class ProteccionDePuestoOcupado
{
    public static function ocupado(Puesto $puesto): bool
    {
        return (bool) (self::calculado($puesto, 'ocupado') ?? self::consultar($puesto)['ocupado']);
    }

    public static function conHistoria(Puesto $puesto): bool
    {
        return (bool) (self::calculado($puesto, 'con_historia') ?? self::consultar($puesto)['con_historia']);
    }

    public static function puedeEliminarse(Puesto $puesto): bool
    {
        return ! self::ocupado($puesto) && ! self::conHistoria($puesto);
    }

    /**
     * Lo que la edición directa no puede hacer con el puesto ocupado.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function validarEdicion(Puesto $puesto, array $datos): void
    {
        $plazasOcupadas = $puesto->plazasOcupadas();

        if (array_key_exists('plazas', $datos) && (int) $datos['plazas'] < $plazasOcupadas) {
            throw new ReglaNegocioException(
                "El puesto tiene {$plazasOcupadas} plaza(s) ocupada(s): no puede quedar con menos."
            );
        }

        if (! self::ocupado($puesto)) {
            return;
        }

        if (array_key_exists('unidad_administrativa_id', $datos)
            && (int) $datos['unidad_administrativa_id'] !== (int) $puesto->unidad_administrativa_id
        ) {
            throw new ReglaNegocioException(
                'El puesto está ocupado: pasa a otra unidad con un traspaso de puesto (LOSEP Art. 37), que emite '
                    .'el acto de cada ocupante. Use «Traspasar a otra unidad».'
            );
        }

        if (array_key_exists('regimen_laboral', $datos)
            && (string) $datos['regimen_laboral'] !== (string) $puesto->regimen_laboral
        ) {
            throw new ReglaNegocioException('El puesto está ocupado: su régimen laboral no cambia mientras lo esté.');
        }

        foreach (['grupo_ocupacional_id' => 'grado', 'cargo_id' => 'denominación'] as $campo => $que) {
            if (array_key_exists($campo, $datos) && (int) $datos[$campo] !== (int) $puesto->{$campo}) {
                throw new ReglaNegocioException(
                    "El puesto está ocupado: su {$que} cambia con la revisión de la clasificación (LOSEP Arts. 61 y "
                        .'62), que emite el acto de cada ocupante. Use «Revisar la clasificación».'
                );
            }
        }

        if (array_key_exists('activo', $datos) && ! filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN) && $puesto->activo) {
            throw new ReglaNegocioException('El puesto está ocupado: no se desactiva mientras lo esté.');
        }
    }

    /**
     * ¿Alguno de los puestos del cargo está ocupado? Renombrarlo cambia la
     * denominación de todos ellos.
     */
    public static function cargoConPuestosOcupados(int $cargoId): bool
    {
        return Puesto::query()->where('cargo_id', $cargoId)->conOcupacion()->get()
            ->contains(fn (Puesto $p) => (bool) $p->getAttribute('ocupado'));
    }

    public static function validarEliminacion(Puesto $puesto): void
    {
        if (self::ocupado($puesto)) {
            throw new ReglaNegocioException('El puesto está ocupado: no se elimina.');
        }

        if (self::conHistoria($puesto)) {
            throw new ReglaNegocioException(
                'El puesto tiene historia —contratos o acciones de personal que lo nombran—: no se elimina. '
                    .'Desactívelo.'
            );
        }
    }

    /** Lo que trajo el listado, si lo trajo (`conOcupacion()`). */
    private static function calculado(Puesto $puesto, string $columna): mixed
    {
        return array_key_exists($columna, $puesto->getAttributes()) ? $puesto->getAttribute($columna) : null;
    }

    /** @return array{ocupado: bool, con_historia: bool} */
    private static function consultar(Puesto $puesto): array
    {
        $fila = Puesto::withTrashed()->whereKey($puesto->id)->conOcupacion()->first();

        return [
            'ocupado'      => (bool) $fila?->getAttribute('ocupado'),
            'con_historia' => (bool) $fila?->getAttribute('con_historia'),
        ];
    }
}
