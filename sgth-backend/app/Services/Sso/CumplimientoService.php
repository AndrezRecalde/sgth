<?php

namespace App\Services\Sso;

use App\Models\Sso\NormativaLegalSso;
use App\Models\Sso\CumplimientoNormativa;
use Illuminate\Database\Eloquent\Collection;

final class CumplimientoService
{
    // ── Catálogo de normativa legal ───────────────────────────────

    public function listarNormativas(array $filtros): Collection
    {
        return NormativaLegalSso::query()
            ->when(isset($filtros['tipo']), fn($q) => $q->where('tipo', $filtros['tipo']))
            ->when(
                $filtros['solo_activas'] ?? true,
                fn($q) => $q->where('activo', true)
            )
            ->orderBy('nombre')
            ->get();
    }

    public function registrarNormativa(array $datos): NormativaLegalSso
    {
        return NormativaLegalSso::create($datos);
    }

    public function actualizarNormativa(int $id, array $datos): NormativaLegalSso
    {
        $normativa = NormativaLegalSso::findOrFail($id);
        $normativa->update($datos);
        return $normativa->fresh();
    }

    public function eliminarNormativa(int $id): void
    {
        NormativaLegalSso::findOrFail($id)->delete();
    }

    // ── Cumplimiento por período ──────────────────────────────────

    public function registrarCumplimiento(array $datos): CumplimientoNormativa
    {
        $datos['registrado_por'] = auth()->id();

        return CumplimientoNormativa::updateOrCreate(
            [
                'normativa_legal_sso_id' => $datos['normativa_legal_sso_id'],
                'periodo' => $datos['periodo'],
            ],
            [
                'estado' => $datos['estado'],
                'observaciones' => $datos['observaciones'] ?? null,
                'registrado_por' => $datos['registrado_por'],
            ]
        );
    }

    /**
     * Lista de verificación de cumplimiento: todas las normativas activas × su estado en el
     * período dado. Las normativas sin registro de cumplimiento para el período se marcan
     * como 'no_registrado' en lugar de omitirse, para que el vacío sea visible.
     */
    public function listaVerificacion(string $periodo): array
    {
        $normativas = NormativaLegalSso::where('activo', true)->orderBy('nombre')->get();

        $cumplimientos = CumplimientoNormativa::where('periodo', $periodo)
            ->whereIn('normativa_legal_sso_id', $normativas->pluck('id'))
            ->get()
            ->keyBy('normativa_legal_sso_id');

        $filas = $normativas->map(function (NormativaLegalSso $normativa) use ($cumplimientos) {
            $cumplimiento = $cumplimientos->get($normativa->id);

            return [
                'normativa' => $normativa,
                'cumplimiento' => $cumplimiento,
                'estado' => $cumplimiento?->estado?->value ?? 'no_registrado',
            ];
        });

        return [
            'periodo' => $periodo,
            'filas' => $filas->values(),
            'totales' => [
                'total' => $filas->count(),
                'cumple' => $filas->where('estado', 'cumple')->count(),
                'no_cumple' => $filas->where('estado', 'no_cumple')->count(),
                'en_proceso' => $filas->where('estado', 'en_proceso')->count(),
                'no_registrado' => $filas->where('estado', 'no_registrado')->count(),
            ],
        ];
    }
}
