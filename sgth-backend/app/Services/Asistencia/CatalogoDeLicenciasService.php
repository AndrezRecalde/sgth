<?php

namespace App\Services\Asistencia;

use App\Enums\CausaLicencia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\ContratoColectivoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Las causas de licencia que tiene un servidor en una fecha, con sus topes
 * (fases 5.1 y 5.3).
 *
 * Las de la ley valen para todos. A un obrero, el contrato colectivo que rige
 * con el dictamen de Finanzas puede ampliarle los días de una causa o darle
 * una que la ley no tiene. Sin dictamen el contrato se tiene por inexistente
 * (Acuerdo MDT-2024-080), y valen los días de la ley.
 */
class CatalogoDeLicenciasService
{
    public function __construct(private ContratoColectivoService $contratos) {}

    /** «1 día», «3 días»: una cláusula de un día es lo más común. */
    public static function enDias(int $n): string
    {
        return $n === 1 ? '1 día' : "{$n} días";
    }

    /**
     * El contrato colectivo que rige para ese servidor en esa fecha, con sus
     * cláusulas. Si es obrero y no hay contrato que aplicar, el aviso dice por qué.
     *
     * @return array{obrero: bool, contrato: ?ContratoColectivo, aviso: ?string}
     */
    public function contratoPara(int $servidorId, CarbonInterface $fecha): array
    {
        $vinculo = Servidor::with('contratoVigente')->find($servidorId)?->contratoVigente;

        if (! $vinculo?->tipo_nombramiento?->esCodigoTrabajo()) {
            return ['obrero' => false, 'contrato' => null, 'aviso' => null];
        }

        $contrato = $this->contratos->vigente($fecha);

        if ($contrato) {
            return ['obrero' => true, 'contrato' => $contrato->load('clausulasDeLicencia.contratoColectivo'), 'aviso' => null];
        }

        $sinDictamen = ContratoColectivo::vigentesEn($fecha)->orderByDesc('vigencia_desde')->orderByDesc('id')->first();

        return [
            'obrero'   => true,
            'contrato' => null,
            'aviso'    => $sinDictamen
                ? "El contrato colectivo N.º {$sinDictamen->numero} no tiene el dictamen del Ministerio de Finanzas: "
                    .'se tiene por inexistente (Acuerdo MDT-2024-080), y valen los días de la ley.'
                : null,
        ];
    }

    /**
     * El tope de la licencia y la cláusula que la respalda, si la hay. La
     * pactada exige su cláusula, del contrato que rige en la fecha de inicio;
     * en una causa de la ley, la cláusula sale sola si da más días.
     *
     * @return array{tope: ?int, clausula: ?ClausulaDeLicencia}
     */
    public function resolver(CausaLicencia $causa, ?string $alcance, ?int $clausulaId, int $servidorId, CarbonInterface $inicio): array
    {
        $aplica = $this->contratoPara($servidorId, $inicio);

        if ($causa !== CausaLicencia::PACTADA) {
            $clausula = $aplica['contrato'] ? $this->ampliacion($aplica['contrato'], $causa, $alcance) : null;

            return ['tope' => $clausula?->dias ?? $causa->tope($alcance), 'clausula' => $clausula];
        }

        if (! $aplica['obrero']) {
            throw new ReglaNegocioException(
                'Las licencias del contrato colectivo son de los obreros: este servidor no está bajo el Código del Trabajo.'
            );
        }

        if (! $aplica['contrato']) {
            throw new ReglaNegocioException(
                $aplica['aviso'] ?? 'No rige un contrato colectivo en la fecha de inicio: no hay licencia pactada que registrar.'
            );
        }

        $clausula = $aplica['contrato']->clausulasDeLicencia->firstWhere('id', $clausulaId);

        if (! $clausula?->esCausaNueva()) {
            throw new ReglaNegocioException(
                'Elija la cláusula del contrato colectivo que concede la licencia: la del contrato que rige en la fecha de inicio.'
            );
        }

        return ['tope' => $clausula->dias, 'clausula' => $clausula];
    }

    /**
     * La cláusula que amplía esa causa y ese caso, si da más días que la ley.
     * La del caso exacto manda sobre la que amplía la causa sin distinguir.
     */
    public function ampliacion(ContratoColectivo $contrato, CausaLicencia $causa, ?string $alcance): ?ClausulaDeLicencia
    {
        $legal = $causa->tope($alcance);

        if ($legal === null) {
            return null;
        }

        $delaCausa = $contrato->clausulasDeLicencia->filter(fn (ClausulaDeLicencia $c) => $c->causa === $causa);
        $clausula = $delaCausa->first(fn (ClausulaDeLicencia $c) => $alcance !== null && $c->alcance === $alcance)
            ?? $delaCausa->first(fn (ClausulaDeLicencia $c) => $c->alcance === null);

        return $clausula && $clausula->dias > $legal ? $clausula : null;
    }

    /**
     * Para el formulario: las causas con sus topes y sus casos. Con servidor,
     * las de su contrato colectivo, y el aviso si no se le aplica; sin
     * servidor —los filtros—, la pactada como una causa más.
     *
     * @return array{causas: list<array<string, mixed>>, meta: array{obrero: bool, contrato_colectivo: ?array{id: int, numero: string}, aviso: ?string}}
     */
    public function catalogo(?int $servidorId = null, CarbonInterface|string|null $fecha = null): array
    {
        $dia = Carbon::parse($fecha ?? now())->startOfDay();
        $aplica = $servidorId
            ? $this->contratoPara($servidorId, $dia)
            : ['obrero' => false, 'contrato' => null, 'aviso' => null];
        $contrato = $aplica['contrato'];

        $causas = array_map(fn (CausaLicencia $c) => $this->deLaLey($c, $contrato), CausaLicencia::legales());

        if ($contrato) {
            foreach ($contrato->clausulasDeLicencia->filter->esCausaNueva() as $clausula) {
                $causas[] = $this->pactada($clausula);
            }
        } elseif ($servidorId === null) {
            $causas[] = [
                ...$this->forma(CausaLicencia::PACTADA, null),
                'alcances' => [],
            ];
        }

        return [
            'causas' => $causas,
            'meta'   => [
                'obrero'             => $aplica['obrero'],
                'contrato_colectivo' => $contrato ? ['id' => $contrato->id, 'numero' => $contrato->numero] : null,
                'aviso'              => $aplica['aviso'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function deLaLey(CausaLicencia $causa, ?ContratoColectivo $contrato): array
    {
        $amplia = $contrato ? $this->ampliacion($contrato, $causa, null) : null;

        return [
            ...$this->forma($causa, $amplia),
            'alcances' => array_map(function (string $codigo, array $caso) use ($causa, $contrato) {
                $clausula = $contrato ? $this->ampliacion($contrato, $causa, $codigo) : null;

                return [
                    'codigo'     => $codigo,
                    'etiqueta'   => $caso['etiqueta'],
                    'tope'       => $clausula?->dias ?? $causa->tope($codigo),
                    'tope_anual' => $clausula?->dias_por_anio,
                    'base_legal' => $causa->baseLegal().($clausula ? '; '.$clausula->baseLegal() : ''),
                ];
            }, array_keys($causa->alcances()), $causa->alcances()),
        ];
    }

    /** @return array<string, mixed> */
    private function pactada(ClausulaDeLicencia $clausula): array
    {
        return [
            'codigo'        => CausaLicencia::PACTADA->value,
            'clausula_id'   => $clausula->id,
            'etiqueta'      => (string) $clausula->nombre,
            'base_legal'    => $clausula->baseLegal(),
            'explicacion'   => $this->explicacionPactada($clausula),
            'tope'          => $clausula->dias,
            'tope_anual'    => $clausula->dias_por_anio,
            'exige_alcance' => false,
            'alcances'      => [],
        ];
    }

    /**
     * Lo común a una causa de la ley, ampliada o no por el contrato.
     *
     * @return array<string, mixed>
     */
    private function forma(CausaLicencia $causa, ?ClausulaDeLicencia $amplia): array
    {
        return [
            'codigo'        => $causa->value,
            'clausula_id'   => null,
            'etiqueta'      => $causa->etiqueta(),
            'base_legal'    => $causa->baseLegal().($amplia ? '; '.$amplia->baseLegal() : ''),
            'explicacion'   => $amplia
                ? self::enDias($amplia->dias)." por el contrato colectivo ({$amplia->clausula}); la ley da {$causa->tope()}."
                : $causa->explicacionDelTope(),
            'tope'          => $amplia?->dias ?? $causa->tope(),
            'tope_anual'    => $amplia?->dias_por_anio,
            'exige_alcance' => $causa->exigeAlcance(),
        ];
    }

    private function explicacionPactada(ClausulaDeLicencia $clausula): string
    {
        return self::enDias($clausula->dias).' por licencia'
            .($clausula->dias_por_anio ? ', hasta '.self::enDias($clausula->dias_por_anio).' al año' : '')
            ." ({$clausula->clausula}).";
    }
}
