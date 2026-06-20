<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Models\Dispensario\AtencionEnfermeria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AtencionEnfermeriaService implements AtencionEnfermeriaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = AtencionEnfermeria::with([
            'enfermera', 'servidor', 'cargaFamiliar.servidor',
            'catalogoServicio',
        ])->orderBy('atendido_en', 'desc');

        if (!empty($filtros['enfermera_id'])) {
            $query->where('enfermera_id', $filtros['enfermera_id']);
        }

        if (!empty($filtros['fecha'])) {
            $query->whereDate('atendido_en', $filtros['fecha']);
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function registrar(
        array $datos,
        int $enfermeraId
    ): AtencionEnfermeria {
        return DB::transaction(function () use ($datos, $enfermeraId) {
            $folio = $this->generarFolio();

            $atencion = AtencionEnfermeria::create([
                ...$datos,
                'folio'        => $folio,
                'enfermera_id' => $enfermeraId,
                'atendido_en'  => now(),
                'created_by'   => $enfermeraId,
            ]);

            return $atencion->load([
                'enfermera', 'servidor', 'cargaFamiliar.servidor',
                'catalogoServicio',
            ]);
        });
    }

    private function generarFolio(): string
    {
        $anio = now()->year;
        $cantidadActual = AtencionEnfermeria::whereYear(
            'created_at', $anio
        )->count();
        $secuencial = str_pad(
            $cantidadActual + 1, 5, '0', STR_PAD_LEFT
        );
        return "ENF-{$anio}-{$secuencial}";
    }
}
