<?php

namespace App\Services\Sso;

use App\Exceptions\ReglaNegocioException;
use App\Models\Sso\NormativaLegalSso;
use App\Models\Sso\CumplimientoNormativa;
use Illuminate\Database\Eloquent\Collection;

final class CumplimientoService
{
    // ── Catálogo de normativa legal ───────────────────────────────

    /**
     * Parámetros tipados y no un arreglo de filtros: `solo_activas` llegaba
     * desde la query string como la CADENA 'false' —truthy en PHP—, así que el
     * filtro se aplicaba igual y no había forma de listar una normativa
     * inactiva. Con un `bool` en la firma, el error no puede volver.
     */
    public function listarNormativas(?string $tipo = null, bool $soloActivas = true): Collection
    {
        return NormativaLegalSso::query()
            ->when($tipo !== null, fn($q) => $q->where('tipo', $tipo))
            ->when($soloActivas, fn($q) => $q->where('activo', true))
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

    /**
     * No se borra una normativa que ya tiene cumplimiento registrado.
     *
     * La FK de `cumplimiento_normativa` es `cascadeOnDelete`, así que el
     * borrado se llevaba en silencio el historial de cumplimiento de todos los
     * períodos —justo la evidencia que una auditoría de SSO viene a pedir— sin
     * que nada en la pantalla lo advirtiera. Quien quiera retirarla del
     * catálogo la marca inactiva: deja de aparecer en la lista de verificación
     * y conserva lo registrado.
     */
    public function eliminarNormativa(int $id): void
    {
        $normativa = NormativaLegalSso::findOrFail($id);

        if ($normativa->cumplimientos()->exists()) {
            throw new ReglaNegocioException(
                'No se puede eliminar la normativa porque tiene cumplimiento registrado en uno o más períodos. '
                . 'Márquela como inactiva para retirarla de la lista de verificación sin perder el historial.'
            );
        }

        $normativa->delete();
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
