<?php

namespace App\Services\Estructura;

use App\Enums\RolPuesto;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\EscalaRemuneracion;
use App\Models\Estructura\EscalaRemuneracionVinculo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\User;
use App\Services\Expediente\OcupantesDelPuesto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La nueva escala de remuneraciones de la LOSEP (diseño de Acciones de
 * Personal, fase 7.3b).
 *
 * Es un acto general: la resolución que fija la RMU nueva de uno o varios
 * grados, dentro de los pisos y techos del Ministerio del Trabajo. No lleva una
 * acción de personal por servidor mientras Talento Humano no diga otra cosa;
 * cada vínculo que sube deja su constancia en la bitácora.
 *
 * - Solo grados de la LOSEP. Los de las autoridades electas no: su
 *   remuneración la fija la ley, como en la revisión de la clasificación
 *   (7.1b). Ni el de los obreros, que suben con el incremento en lote (4.6).
 * - Ningún grado baja, y ningún vínculo: quien ya cobra la RMU nueva o más,
 *   se queda como está.
 * - Rige hoy o antes: las escalas suelen ser retroactivas, y una que rige más
 *   adelante se registra el día en que rige.
 * - No se registra con acciones en trámite o por regir sobre un puesto de esos
 *   grados o sus ocupantes: se registrarían después con la cifra vieja.
 */
class EscalaRemuneracionService
{
    /** ¿Puede subir este grado con una escala? Null si sí; si no, por qué. */
    public function impedimentoDelGrado(GrupoOcupacional $grado): ?string
    {
        return match (true) {
            ! $grado->activo                                => "El grado {$grado->grado_codigo} está inactivo.",
            $grado->esCodigoTrabajo()                       => "El grado {$grado->grado_codigo} es de los obreros: su remuneración sube con el incremento en lote del Código del Trabajo.",
            $grado->rol_puesto === RolPuesto::DIGNATARIO    => "El grado {$grado->grado_codigo} es el de una autoridad de elección popular: su remuneración la fija la ley.",
            ! $grado->esLosep()                             => "El grado {$grado->grado_codigo} no es de la LOSEP.",
            default                                         => null,
        };
    }

    /**
     * Lo que haría la escala: los grados con su RMU de antes y la nueva, los
     * vínculos que suben y lo que impide registrarla.
     *
     * @param  array{grados: list<array{grupo_ocupacional_id: int, rmu_nueva: float|string}>}  $datos
     */
    public function previsualizar(array $datos, ?User $usuario): array
    {
        $plan = $this->plan($datos['grados'], $usuario);

        return [
            'grados'       => $plan['grados']->map(fn (array $g) => [
                'grupo_ocupacional_id' => $g['grado']->id,
                'grado'                => $g['grado']->grado_codigo,
                'denominacion'         => $g['grado']->denominacion_generica,
                'rmu_anterior'         => $g['anterior'],
                'rmu_nueva'            => $g['nueva'],
                'puestos'              => $g['puestos']->count(),
                'vinculos'             => $plan['vinculos']->where('grado.id', $g['grado']->id)->count(),
            ])->values()->all(),
            'vinculos'     => $plan['vinculos']->map(fn (array $v) => [
                'contrato_id'           => $v['contrato']->id,
                'servidor_id'           => $v['contrato']->servidor_id,
                'servidor'              => OcupantesDelPuesto::nombre($v['contrato']->servidor),
                'cedula'                => $v['contrato']->servidor?->cedula,
                'puesto'                => $v['contrato']->puesto?->cargo?->nombre,
                'grado'                 => $v['grado']->grado_codigo,
                'remuneracion_anterior' => $v['anterior'],
                'remuneracion_nueva'    => $v['nueva'],
                'sube'                  => $v['sube'],
            ])->values()->all(),
            'impedimentos' => $plan['impedimentos'],
        ];
    }

    /**
     * Registra la escala: la RMU nueva en el catálogo y en cada vínculo que
     * sube, con su constancia en la bitácora. Todo o nada.
     *
     * @param  array{instrumento: string, fecha_instrumento: string, rige_desde: string,
     *               dictamen_presupuestario_ref: string, descripcion?: ?string,
     *               grados: list<array{grupo_ocupacional_id: int, rmu_nueva: float|string}>}  $datos
     */
    public function registrar(array $datos, User $usuario): EscalaRemuneracion
    {
        if (Carbon::parse($datos['rige_desde'])->gt(now()->endOfDay())) {
            throw new ReglaNegocioException(
                'La escala rige hoy o antes: si rige más adelante, se registra el día en que rige.'
            );
        }

        return DB::transaction(function () use ($datos, $usuario) {
            GrupoOcupacional::whereIn('id', array_column($datos['grados'], 'grupo_ocupacional_id'))->lockForUpdate()->get();

            $plan = $this->plan($datos['grados'], $usuario);

            if ($plan['impedimentos'] !== []) {
                throw new ReglaNegocioException(implode(' ', $plan['impedimentos']));
            }

            $escala = EscalaRemuneracion::create([
                'instrumento'                 => $datos['instrumento'],
                'fecha_instrumento'           => $datos['fecha_instrumento'],
                'rige_desde'                  => $datos['rige_desde'],
                'dictamen_presupuestario_ref' => $datos['dictamen_presupuestario_ref'],
                'descripcion'                 => $datos['descripcion'] ?? null,
                'registrada_por'              => $usuario->id,
                'registrada_en'               => now(),
            ]);

            foreach ($plan['grados'] as $g) {
                $escala->grados()->create([
                    'grupo_ocupacional_id' => $g['grado']->id,
                    'rmu_anterior'         => $g['anterior'],
                    'rmu_nueva'            => $g['nueva'],
                ]);
                $g['grado']->update(['rmu' => $g['nueva']]);
            }

            foreach ($plan['vinculos']->where('sube', true) as $v) {
                /** @var ContratoServidor $contrato */
                $contrato = $v['contrato'];

                $escala->vinculos()->create([
                    'contrato_id'           => $contrato->id,
                    'servidor_id'           => $contrato->servidor_id,
                    'grupo_ocupacional_id'  => $v['grado']->id,
                    'remuneracion_anterior' => $v['anterior'],
                    'remuneracion_nueva'    => $v['nueva'],
                ]);
                $contrato->update(['remuneracion' => $v['nueva']]);

                $this->anotar($contrato, $escala, TipoEventoVinculo::ESCALA_APLICADA, $v['anterior'], $v['nueva'],
                    "Nueva escala de remuneraciones ({$escala->instrumento}): desde el {$escala->rige_desde->format('d/m/Y')} "
                        ."cobra {$this->dinero($v['nueva'])}, antes {$this->dinero($v['anterior'])}.", $usuario);
            }

            return $escala->fresh(['grados', 'vinculos']);
        });
    }

    /**
     * La escala registrada por error: el catálogo y cada vínculo vuelven a lo
     * de antes, con su constancia. Entera, y solo si nada cambió después: un
     * grado con otra escala encima o un vínculo con otra remuneración no se
     * pisan.
     */
    public function revertir(EscalaRemuneracion $escala, string $motivo, User $usuario): EscalaRemuneracion
    {
        return DB::transaction(function () use ($escala, $motivo, $usuario) {
            $escala = EscalaRemuneracion::with(['grados.grupoOcupacional', 'vinculos.contrato.servidor'])
                ->lockForUpdate()
                ->findOrFail($escala->id);

            if ($escala->estaRevertida()) {
                throw new ReglaNegocioException('La escala ya se revirtió.');
            }

            $impedimentos = [];

            foreach ($escala->grados as $g) {
                if (abs((float) $g->grupoOcupacional->rmu - (float) $g->rmu_nueva) >= 0.005) {
                    $impedimentos[] = "El grado {$g->grupoOcupacional->grado_codigo} ya no tiene la RMU de esta escala: cambió después.";
                }
            }

            foreach ($escala->vinculos as $v) {
                $contrato = $v->contrato;
                $cambio = match (true) {
                    $contrato === null => 'su vínculo ya no existe',
                    abs((float) $contrato->remuneracion - (float) $v->remuneracion_nueva) >= 0.005 => 'su remuneración cambió después',
                    default => null,
                };

                if ($cambio) {
                    $impedimentos[] = OcupantesDelPuesto::nombre($contrato?->servidor ?? $v->servidor).": {$cambio}.";
                }
            }

            if ($impedimentos !== []) {
                throw new ReglaNegocioException('No se puede revertir la escala. '.implode(' ', $impedimentos));
            }

            foreach ($escala->grados as $g) {
                $g->grupoOcupacional->update(['rmu' => $g->rmu_anterior]);
            }

            foreach ($escala->vinculos as $v) {
                $v->contrato->update(['remuneracion' => $v->remuneracion_anterior]);

                $this->anotar($v->contrato, $escala, TipoEventoVinculo::ESCALA_REVERTIDA,
                    (float) $v->remuneracion_nueva, $v->remuneracion_anterior !== null ? (float) $v->remuneracion_anterior : null,
                    "Se revirtió la escala de remuneraciones ({$escala->instrumento}): vuelve a "
                        ."{$this->dinero($v->remuneracion_anterior !== null ? (float) $v->remuneracion_anterior : null)}. {$motivo}", $usuario);
            }

            $escala->update([
                'revertida_por'    => $usuario->id,
                'revertida_en'     => now(),
                'motivo_reversion' => $motivo,
            ]);

            return $escala->fresh(['grados', 'vinculos']);
        });
    }

    /** Las escalas registradas, la más reciente primero. */
    public function listar(): array
    {
        return EscalaRemuneracion::query()
            ->with(['grados.grupoOcupacional:id,grado_codigo,denominacion_generica', 'registradaPor.servidor:id,nombre,apellido'])
            ->withCount('vinculos')
            ->orderByDesc('registrada_en')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EscalaRemuneracion $e) => [
                'id'                          => $e->id,
                'instrumento'                 => $e->instrumento,
                'fecha_instrumento'           => $e->fecha_instrumento->toDateString(),
                'rige_desde'                  => $e->rige_desde->toDateString(),
                'dictamen_presupuestario_ref' => $e->dictamen_presupuestario_ref,
                'descripcion'                 => $e->descripcion,
                'registrada_en'               => $e->registrada_en->toIso8601String(),
                'registrada_por'              => $e->registradaPor?->servidor
                    ? trim("{$e->registradaPor->servidor->nombre} {$e->registradaPor->servidor->apellido}")
                    : $e->registradaPor?->usuario_ti,
                'revertida_en'                => $e->revertida_en?->toIso8601String(),
                'motivo_reversion'            => $e->motivo_reversion,
                'vinculos'                    => $e->vinculos_count,
                'grados'                      => $e->grados->map(fn ($g) => [
                    'grado'        => $g->grupoOcupacional?->grado_codigo,
                    'denominacion' => $g->grupoOcupacional?->denominacion_generica,
                    'rmu_anterior' => (float) $g->rmu_anterior,
                    'rmu_nueva'    => (float) $g->rmu_nueva,
                ])->values()->all(),
            ])
            ->all();
    }

    // ── El plan ─────────────────────────────────────────────────────

    /**
     * @param  list<array{grupo_ocupacional_id: int, rmu_nueva: float|string}>  $pedidos
     * @return array{grados: Collection<int, array{grado: GrupoOcupacional, anterior: float, nueva: float, puestos: Collection<int, Puesto>}>,
     *               vinculos: Collection<int, array{contrato: ContratoServidor, grado: GrupoOcupacional, anterior: ?float, nueva: float, sube: bool}>,
     *               impedimentos: list<string>}
     */
    private function plan(array $pedidos, ?User $usuario): array
    {
        $impedimentos = [];
        $grados = collect();

        $catalogo = GrupoOcupacional::whereIn('id', array_column($pedidos, 'grupo_ocupacional_id'))->get()->keyBy('id');

        foreach ($pedidos as $pedido) {
            $grado = $catalogo->get((int) $pedido['grupo_ocupacional_id']);

            if (! $grado) {
                continue;
            }

            $anterior = (float) $grado->rmu;
            $nueva = round((float) $pedido['rmu_nueva'], 2);

            if ($motivo = $this->impedimentoDelGrado($grado)) {
                $impedimentos[] = $motivo;

                continue;
            }

            if ($nueva <= $anterior) {
                $impedimentos[] = "La RMU del grado {$grado->grado_codigo} es {$this->dinero($anterior)}: la escala la sube, no la baja ni la deja igual.";

                continue;
            }

            $grados->push([
                'grado'    => $grado,
                'anterior' => $anterior,
                'nueva'    => $nueva,
                'puestos'  => Puesto::where('grupo_ocupacional_id', $grado->id)->with('cargo:id,nombre')->orderBy('id')->get(),
            ]);
        }

        $vinculos = collect();
        $enCurso = collect();

        foreach ($grados as $g) {
            foreach ($g['puestos'] as $puesto) {
                $ocupantes = OcupantesDelPuesto::de($puesto)
                    ->filter(fn (ContratoServidor $c) => $c->tipo_nombramiento?->esLosep()
                        && $c->tipo_nombramiento !== TipoNombramiento::ELECCION_POPULAR
                        && ($c->fecha_fin === null || $c->fecha_fin->gte(now()->startOfDay())));

                foreach ($ocupantes as $contrato) {
                    $contrato->setRelation('puesto', $puesto);
                    $actual = $contrato->remuneracion !== null ? (float) $contrato->remuneracion : null;
                    // Nunca baja: quien ya cobra la RMU nueva o más, se queda.
                    $nueva = max($actual ?? 0.0, $g['nueva']);

                    $vinculos->push([
                        'contrato' => $contrato,
                        'grado'    => $g['grado'],
                        'anterior' => $actual,
                        'nueva'    => $nueva,
                        'sube'     => $actual === null || $nueva - $actual >= 0.005,
                    ]);
                }

                $enCurso = $enCurso->merge(OcupantesDelPuesto::accionesEnCurso($puesto, OcupantesDelPuesto::de($puesto)));
            }
        }

        if ($usuario?->servidor_id && $vinculos->contains(fn (array $v) => (int) $v['contrato']->servidor_id === (int) $usuario->servidor_id)) {
            $impedimentos[] = 'Usted está entre los vínculos que suben con esta escala: la registra otra persona de Talento Humano.';
        }

        $enCurso = $enCurso->unique('id');
        if ($enCurso->isNotEmpty()) {
            $impedimentos[] = 'Hay acciones en trámite o por regir sobre puestos de esos grados o sus ocupantes ('
                .$enCurso->map(fn (MovimientoPersonal $m) => $m->codigo_registro ?? "{$m->etiqueta()} #{$m->id}")->implode(', ')
                .'): se registrarían con la remuneración de antes. Termínelas o anúlelas primero.';
        }

        if ($grados->isEmpty() && $impedimentos === []) {
            $impedimentos[] = 'Indique al menos un grado con su RMU nueva.';
        }

        return ['grados' => $grados, 'vinculos' => $vinculos, 'impedimentos' => $impedimentos];
    }

    private function anotar(
        ContratoServidor $contrato,
        EscalaRemuneracion $escala,
        TipoEventoVinculo $tipo,
        ?float $anterior,
        ?float $nueva,
        string $descripcion,
        User $usuario,
    ): void {
        EventoVinculo::create([
            'servidor_id'          => $contrato->servidor_id,
            'contrato_servidor_id' => $contrato->id,
            'tipo'                 => $tipo,
            'fecha'                => $tipo === TipoEventoVinculo::ESCALA_APLICADA ? $escala->rige_desde->toDateString() : now()->toDateString(),
            'descripcion'          => $descripcion,
            'datos'                => ['escala_id' => $escala->id, 'remuneracion_anterior' => $anterior, 'remuneracion_nueva' => $nueva],
            'registrado_por'       => $usuario->id,
        ]);
    }

    private function dinero(?float $valor): string
    {
        return $valor !== null ? '$'.number_format($valor, 2, ',', '.') : 'sin remuneración guardada';
    }
}
