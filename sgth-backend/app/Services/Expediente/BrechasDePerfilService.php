<?php

namespace App\Services\Expediente;

use App\Enums\EstadoFormacion;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Services\Estructura\ArbolUnidades;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Las brechas de perfil (docs/diseno-manual-de-puestos.md, P7, PR 17): cada
 * servidor con contrato vigente frente al perfil de su puesto. Es un informe
 * para el plan de capacitación; no avisa al nombrar ni al reclasificar (D9).
 *
 * - Instrucción: el título validado más alto del historial académico contra
 *   las alternativas del perfil. Cumple con una que alcance. El área del
 *   título no se compara: el título es texto libre.
 * - Capacitación: los cursos validados contra las temáticas del perfil, que
 *   también son texto libre. Solo se marcan los que comparten palabras: es
 *   una pista para quien revisa, no un «cumple».
 * - La experiencia no se compara: el expediente no guarda la de antes de la
 *   institución.
 */
final class BrechasDePerfilService
{
    public const ESTADOS = ['cumple', 'no_cumple', 'por_revisar', 'sin_requisitos'];

    /** Palabras que aparecen en casi cualquier curso del sector público y no dicen de qué trata. */
    private const VACIAS = [
        'sobre', 'entre', 'desde', 'hasta', 'segun', 'para', 'como', 'manejo', 'gestion', 'sistema',
        'sistemas', 'publico', 'publica', 'publicos', 'publicas', 'sector', 'general', 'basico', 'basica',
        'curso', 'cursos', 'taller', 'talleres', 'seminario', 'tecnica', 'tecnicas', 'aplicacion',
        'proceso', 'procesos', 'institucional', 'nivel', 'conocimiento', 'conocimientos', 'actualizacion',
    ];

    /**
     * @param  array{unidad_administrativa_id?: int|null}  $filtros
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function informe(array $filtros = []): array
    {
        $contratos = ContratoServidor::query()
            ->where('estado', 'vigente')
            ->whereNotNull('puesto_id')
            ->when($filtros['unidad_administrativa_id'] ?? null, fn ($q, $unidad) => $q->whereHas(
                'puesto',
                fn ($p) => $p->whereIn('unidad_administrativa_id', ArbolUnidades::conDescendientes((int) $unidad)),
            ))
            // Solo las columnas que el informe muestra: el servidor completo
            // arrastra datos que aquí no hacen falta.
            ->with([
                'servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido',
                'puesto:id,cargo_id,unidad_administrativa_id,grupo_ocupacional_id',
                'puesto.cargo:id,nombre',
                'puesto.unidadAdministrativa:id,nombre',
                'puesto.grupoOcupacional:id,grado_codigo',
                'puesto.perfil:id,puesto_id,capacitacion',
                'puesto.perfil.requisitos',
            ])
            ->get(['id', 'servidor_id', 'puesto_id', 'estado']);

        $historial = HistorialAcademicoServidor::query()
            ->whereIn('servidor_id', $contratos->pluck('servidor_id')->unique())
            ->get(['id', 'servidor_id', 'tipo_estudio', 'nivel_estudio', 'titulo_capacitacion', 'institucion', 'fecha_fin', 'estado'])
            ->groupBy('servidor_id');

        $filas = $contratos
            ->filter(fn (ContratoServidor $c) => $c->servidor !== null && $c->puesto !== null)
            ->map(fn (ContratoServidor $c) => $this->fila($c, $historial->get($c->servidor_id, collect())))
            ->sortBy([
                fn (array $a, array $b) => self::clave($a['puesto']['unidad']) <=> self::clave($b['puesto']['unidad']),
                fn (array $a, array $b) => self::clave($a['puesto']['cargo']) <=> self::clave($b['puesto']['cargo']),
                fn (array $a, array $b) => self::clave($a['servidor']['nombre']) <=> self::clave($b['servidor']['nombre']),
                fn (array $a, array $b) => $a['id'] <=> $b['id'],
            ])
            ->values();

        $resumen = ['total' => $filas->count()];
        foreach (self::ESTADOS as $estado) {
            $resumen[$estado] = $filas->where('instruccion.estado', $estado)->count();
        }
        // De los que tienen temáticas en su perfil, a cuántos no se les
        // encontró ningún curso relacionado.
        $resumen['sin_capacitacion_relacionada'] = $filas
            ->filter(fn (array $f) => $f['capacitacion']['tematicas'] !== null && $f['capacitacion']['relacionadas'] === [])
            ->count();

        return ['filas' => $filas->all(), 'resumen' => $resumen];
    }

    /** @return array<string, mixed> */
    private function fila(ContratoServidor $contrato, Collection $historial): array
    {
        $servidor = $contrato->servidor;
        $puesto   = $contrato->puesto;
        $validado = $historial->filter(fn (HistorialAcademicoServidor $h) => $h->estado === EstadoFormacion::VALIDADO);

        return [
            'id'       => $contrato->id,
            'servidor' => [
                'id'     => $servidor->id,
                'cedula' => $servidor->cedula,
                'nombre' => trim(implode(' ', array_filter([
                    $servidor->apellido, $servidor->segundo_apellido, $servidor->nombre, $servidor->segundo_nombre,
                ]))),
            ],
            'puesto' => [
                'id'     => $puesto->id,
                'cargo'  => $puesto->cargo?->nombre,
                'unidad' => $puesto->unidadAdministrativa?->nombre,
                'grado'  => $puesto->grupoOcupacional?->grado_codigo,
            ],
            'instruccion'  => $this->instruccion(
                $puesto->perfil?->requisitos ?? collect(),
                $validado->where('tipo_estudio', TipoEstudio::ESTUDIO),
                $historial->filter(fn (HistorialAcademicoServidor $h) => $h->estado === EstadoFormacion::POR_VALIDAR)->count(),
            ),
            'capacitacion' => $this->capacitacion(
                $puesto->perfil?->capacitacion,
                $validado->where('tipo_estudio', TipoEstudio::CAPACITACION),
            ),
        ];
    }

    /**
     * Cumple si su título validado más alto alcanza alguna alternativa. Los
     * «estudios superiores en curso» no tienen título con qué compararse: un
     * tercer nivel los cubre; si no, quedan por revisar.
     *
     * @return array<string, mixed>
     */
    private function instruccion(Collection $requisitos, Collection $estudios, int $porValidar): array
    {
        $maximo = $estudios
            ->map(fn (HistorialAcademicoServidor $h) => $h->nivel_estudio)
            ->filter()
            ->sortByDesc(fn (NivelEstudio $n) => self::rango($n))
            ->first();

        $pide = $requisitos->map(fn (PuestoRequisitoInstruccion $r) => $r->nivel_instruccion->etiqueta().($r->detalle ? " ({$r->detalle})" : ''))
            ->values()->all();

        if ($requisitos->isEmpty()) {
            $estado = 'sin_requisitos';
        } else {
            $cumple = false;
            $sinComparar = false;
            foreach ($requisitos as $requisito) {
                $pedido = $requisito->nivel_instruccion->nivelDeEstudio();
                // Solo los estudios en curso no tienen título (`nivelDeEstudio()`).
                if ($pedido === null) {
                    $pedido = NivelEstudio::TERCER_NIVEL;
                    $sinComparar = true;
                }
                if ($maximo !== null && self::rango($maximo) >= self::rango($pedido)) {
                    $cumple = true;
                }
            }
            $estado = $cumple ? 'cumple' : ($sinComparar ? 'por_revisar' : 'no_cumple');
        }

        return [
            'estado'      => $estado,
            'tiene'       => $maximo?->etiqueta(),
            'pide'        => $pide,
            'por_validar' => $porValidar,
        ];
    }

    /**
     * Las temáticas del perfil y los cursos validados del servidor, con los
     * que comparten alguna palabra que diga de qué tratan.
     *
     * @return array<string, mixed>
     */
    private function capacitacion(?string $tematicas, Collection $cursos): array
    {
        $tematicas = filled($tematicas) ? trim($tematicas) : null;
        $raices    = $tematicas ? self::raices($tematicas) : [];

        $relacionadas = $tematicas === null ? [] : $cursos
            ->filter(fn (HistorialAcademicoServidor $c) => filled($c->titulo_capacitacion)
                && array_intersect(self::raices($c->titulo_capacitacion), $raices) !== [])
            ->sortByDesc(fn (HistorialAcademicoServidor $c) => $c->fecha_fin?->timestamp ?? 0)
            ->map(fn (HistorialAcademicoServidor $c) => [
                'titulo' => $c->titulo_capacitacion,
                'fecha'  => $c->fecha_fin?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'tematicas'    => $tematicas,
            'registradas'  => $cursos->count(),
            'relacionadas' => $relacionadas,
        ];
    }

    /**
     * Las palabras que dicen de qué trata un texto, recortadas para que
     * «contratación» y «contratos» cuenten igual.
     *
     * @return list<string>
     */
    private static function raices(string $texto): array
    {
        $palabras = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($texto)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            fn (string $p) => substr($p, 0, 6),
            array_filter($palabras, fn (string $p) => strlen($p) >= 5 && ! in_array($p, self::VACIAS, true)),
        )));
    }

    private static function rango(NivelEstudio $nivel): int
    {
        return (int) array_search($nivel, NivelEstudio::cases(), true);
    }

    private static function clave(?string $texto): string
    {
        return Str::lower(Str::ascii((string) $texto));
    }
}
