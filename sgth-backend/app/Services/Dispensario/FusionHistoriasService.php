<?php

namespace App\Services\Dispensario;

use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Junta las historias clínicas de un mismo paciente (hallazgo del 2026-10-23).
 *
 * Quedaron pacientes con dos: la que abría la recepción sin cédula antes del
 * 2026-09-05 y otra posterior, o la de un servidor y la de su preocupacional,
 * suelta a nombre de su cédula. Cada pantalla elegía una y una misma atención
 * llegó a quedar con el triaje en una y la consulta en otra.
 *
 * Se queda la que tiene la cédula del paciente; si ninguna, la que tenga
 * alguna; y entre ellas, la más antigua (la regla de
 * `HistoriaClinica::delDueno()`). Todo lo que cuelga de las demás se muda a
 * ella y las demás se retiran en blando, con la constancia de a cuál se
 * fusionaron. Si dos tienen odontograma no se junta: hay que elegir a mano.
 */
final class FusionHistoriasService
{
    /**
     * Todo lo que cuelga de una historia. `FusionarHistoriasTest` comprueba
     * que no falte ninguna tabla con `historia_clinica_id`.
     */
    public const DEPENDIENTES = [
        'alergias_paciente', 'antecedentes_paciente', 'consultas_medicas', 'odontogramas',
        'ordenes_examen', 'resultados_medicos', 'triajes',
    ];

    /** Lo que la que se queda toma de las demás si no lo tiene. */
    private const DATOS = ['grupo_sanguineo', 'medicacion_habitual'];

    /**
     * Los pacientes con más de una historia viva, cada uno con la que se queda,
     * lo que se mudaría y, si lo hay, por qué no se puede juntar solo.
     *
     * @return Collection<int, array{paciente: string, cedula: ?string, servidor_id: ?int, carga_familiar_id: ?int, historias: Collection<int, HistoriaClinica>, queda: HistoriaClinica, muda: array<string, int>, conflicto: ?string}>
     */
    public function duplicados(): Collection
    {
        $historias = HistoriaClinica::query()->orderBy('id')->get();
        // Las del preocupacional que no se engancharon a nadie, por su cédula.
        $sueltas = $historias
            ->filter(fn (HistoriaClinica $h) => $h->servidor_id === null && $h->carga_familiar_id === null && $h->cedula_paciente)
            ->keyBy('cedula_paciente');

        $grupos = collect();

        $porServidor = $historias->whereNotNull('servidor_id')->groupBy('servidor_id');
        $servidores = Servidor::withTrashed()->whereKey($porServidor->keys())->get()->keyBy('id');
        foreach ($porServidor as $servidorId => $suyas) {
            $servidor = $servidores->get($servidorId);
            $grupos->push($this->grupo(
                $suyas, $sueltas, $servidor?->cedula,
                trim(($servidor?->nombre ?? '') . ' ' . ($servidor?->apellido ?? '')) ?: "Servidor {$servidorId}",
                (int) $servidorId, null,
            ));
        }

        $porCarga = $historias->whereNotNull('carga_familiar_id')->groupBy('carga_familiar_id');
        $cargas = CargaFamiliar::withTrashed()->whereKey($porCarga->keys())->get()->keyBy('id');
        foreach ($porCarga as $cargaId => $suyas) {
            $carga = $cargas->get($cargaId);
            $grupos->push($this->grupo(
                $suyas, $sueltas, $carga?->cedula,
                trim(($carga?->nombres ?? '') . ' ' . ($carga?->apellidos ?? '')) ?: "Familiar {$cargaId}",
                null, (int) $cargaId,
            ));
        }

        return $grupos->filter()->values();
    }

    /**
     * Junta las historias de un paciente en la que se queda.
     *
     * @param array{servidor_id: ?int, carga_familiar_id: ?int, historias: Collection<int, HistoriaClinica>, queda: HistoriaClinica} $grupo
     */
    public function fusionar(array $grupo, string $responsable): HistoriaClinica
    {
        return DB::transaction(function () use ($grupo, $responsable) {
            $ids = $grupo['historias']->pluck('id')->all();
            $vivas = HistoriaClinica::lockForUpdate()->whereKey($ids)->orderBy('id')->get();
            $queda = $vivas->firstWhere('id', $grupo['queda']->id);
            $otras = $vivas->reject(fn (HistoriaClinica $h) => $h->id === $queda?->id)->values();

            if (! $queda || $otras->isEmpty()) {
                return $queda ?? HistoriaClinica::findOrFail($grupo['queda']->id);
            }

            $mudado = [];
            foreach (self::DEPENDIENTES as $tabla) {
                $mudado[$tabla] = DB::table($tabla)
                    ->whereIn('historia_clinica_id', $otras->pluck('id'))
                    ->update(['historia_clinica_id' => $queda->id]);
            }

            $completa = [];
            foreach (self::DATOS as $campo) {
                $valor = $otras->first(fn (HistoriaClinica $h) => filled($h->{$campo}))?->{$campo};
                if (blank($queda->{$campo}) && filled($valor)) {
                    $completa[$campo] = $valor;
                }
            }

            // La que se queda es la del paciente aunque haya sido la suelta del preocupacional.
            $queda->update([
                ...$completa,
                'servidor_id'       => $grupo['servidor_id'] ?? $queda->servidor_id,
                'carga_familiar_id' => $grupo['carga_familiar_id'] ?? $queda->carga_familiar_id,
                'tipo_paciente'     => $grupo['servidor_id'] ? 'servidor' : ($grupo['carga_familiar_id'] ? 'familiar' : $queda->tipo_paciente),
                'numero_historia'   => $queda->numero_historia ?? $queda->cedula_paciente,
            ]);

            foreach ($otras as $otra) {
                $otra->forceFill(['fusionada_con_id' => $queda->id, 'fusionada_en' => now()])->save();
                $otra->delete();
            }

            activity()
                ->performedOn($queda)
                ->withProperties([
                    'fusionadas' => $otras->pluck('id')->all(),
                    'mudado'     => array_filter($mudado),
                    'completado' => array_keys($completa),
                    'responsable' => $responsable,
                ])
                ->event('fusionada')
                ->log('Historias clínicas del mismo paciente fusionadas');

            return $queda->refresh();
        });
    }

    /**
     * El grupo de un paciente: sus historias y la suelta con su cédula, si la
     * hay. Null si tiene una sola.
     *
     * @param Collection<int, HistoriaClinica> $suyas
     * @param Collection<string, HistoriaClinica> $sueltas
     */
    private function grupo(Collection $suyas, Collection $sueltas, ?string $cedula, string $paciente, ?int $servidorId, ?int $cargaId): ?array
    {
        $historias = $suyas->values();
        if ($cedula && $sueltas->has($cedula)) {
            $historias->push($sueltas->pull($cedula));
        }

        if ($historias->count() < 2) {
            return null;
        }

        $queda = $historias->sortBy([
            fn (HistoriaClinica $a, HistoriaClinica $b) => ($b->cedula_paciente !== null && $b->cedula_paciente === $cedula) <=> ($a->cedula_paciente !== null && $a->cedula_paciente === $cedula),
            fn (HistoriaClinica $a, HistoriaClinica $b) => ($b->cedula_paciente !== null) <=> ($a->cedula_paciente !== null),
            fn (HistoriaClinica $a, HistoriaClinica $b) => $a->id <=> $b->id,
        ])->first();
        $otras = $historias->reject(fn (HistoriaClinica $h) => $h->id === $queda->id)->pluck('id');

        $muda = [];
        foreach (self::DEPENDIENTES as $tabla) {
            $muda[$tabla] = DB::table($tabla)->whereIn('historia_clinica_id', $otras)->count();
        }

        // El odontograma es uno por historia (índice único): con dos, hay que elegir a mano.
        $conOdontograma = DB::table('odontogramas')->whereIn('historia_clinica_id', $historias->pluck('id'))
            ->distinct()->count('historia_clinica_id');

        return [
            'paciente'          => $paciente,
            'cedula'            => $cedula,
            'servidor_id'       => $servidorId,
            'carga_familiar_id' => $cargaId,
            'historias'         => $historias->sortBy('id')->values(),
            'queda'             => $queda,
            'muda'              => array_filter($muda),
            'conflicto'         => $conOdontograma > 1
                ? 'Más de una tiene odontograma: elija a mano cuál se queda.'
                : null,
        ];
    }
}
