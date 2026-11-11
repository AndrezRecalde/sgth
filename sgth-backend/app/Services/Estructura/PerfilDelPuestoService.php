<?php

namespace App\Services\Estructura;

use App\Enums\NivelDelPuesto;
use App\Enums\NivelInstruccion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoPerfil;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use Illuminate\Support\Facades\DB;

/**
 * La descripción del puesto, como la ficha del Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P1). Se lee y se guarda entera: la
 * pantalla la edita como un formulario, y las alternativas de instrucción se
 * reemplazan todas, en el orden en que llegan.
 */
class PerfilDelPuestoService
{
    /** El perfil del puesto, o uno vacío si todavía no tiene. */
    public function obtener(Puesto $puesto): PuestoPerfil
    {
        $perfil = $puesto->perfil()->with('requisitos')->first()
            ?? new PuestoPerfil(['puesto_id' => $puesto->id]);

        return $perfil->setRelation('puesto', $puesto);
    }

    /**
     * Guarda lo que llega; lo que no llega se queda como está. Sin nivel, se
     * propone desde la instrucción (`NivelDelPuesto::segunInstruccion`).
     *
     * @param array<string, mixed> $datos
     * @param string $causa Lo que la bitácora dice que cambió el perfil.
     */
    public function guardar(Puesto $puesto, array $datos, string $causa = 'Perfil del puesto'): PuestoPerfil
    {
        return DB::transaction(function () use ($puesto, $datos, $causa) {
            $perfil = $puesto->perfil()->with('requisitos')->first() ?? new PuestoPerfil(['puesto_id' => $puesto->id]);
            $antes = $this->foto($perfil);

            $perfil->fill(array_intersect_key($datos, array_flip(PuestoPerfil::CAMPOS)));
            $perfil->save();

            if (array_key_exists('requisitos', $datos)) {
                $perfil->requisitos()->delete();
                foreach (array_values($datos['requisitos'] ?? []) as $i => $requisito) {
                    $perfil->requisitos()->create([
                        'nivel_instruccion' => $requisito['nivel_instruccion'],
                        'detalle'           => $requisito['detalle'] ?? null,
                        'meses_experiencia' => (int) $requisito['meses_experiencia'],
                        'orden'             => $i + 1,
                    ]);
                }
            }

            $perfil->load('requisitos');

            if ($perfil->nivel === null) {
                $sugerido = NivelDelPuesto::segunInstruccion(
                    $perfil->requisitos->map(fn (PuestoRequisitoInstruccion $r) => $r->nivel_instruccion),
                );
                if ($sugerido) {
                    $perfil->update(['nivel' => $sugerido]);
                }
            }

            $despues = $this->foto($perfil);
            if ($antes !== $despues) {
                activity()
                    ->performedOn($puesto)
                    ->event('updated')
                    ->withProperties([
                        'antes'   => array_diff_assoc($antes, $despues),
                        'despues' => array_diff_assoc($despues, $antes),
                        'causa'   => $causa,
                    ])
                    ->log('Perfil del puesto actualizado');
            }

            return $perfil->setRelation('puesto', $puesto);
        });
    }

    /**
     * Lo que la bitácora compara: los campos y las alternativas de
     * instrucción, como texto.
     *
     * @return array<string, mixed>
     */
    private function foto(PuestoPerfil $perfil): array
    {
        $campos = [];
        foreach (PuestoPerfil::CAMPOS as $campo) {
            $valor = $perfil->getAttribute($campo);
            $campos[$campo] = $valor instanceof NivelDelPuesto ? $valor->value : $valor;
        }

        $campos['requisitos'] = $perfil->relationLoaded('requisitos')
            ? $perfil->requisitos->map(fn (PuestoRequisitoInstruccion $r) => sprintf(
                '%s%s, %d meses',
                $r->nivel_instruccion instanceof NivelInstruccion ? $r->nivel_instruccion->value : $r->nivel_instruccion,
                $r->detalle ? " ({$r->detalle})" : '',
                $r->meses_experiencia,
            ))->implode('; ')
            : '';

        return $campos;
    }
}
