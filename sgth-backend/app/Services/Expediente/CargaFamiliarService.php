<?php

namespace App\Services\Expediente;

use App\Models\Expediente\CargaFamiliar;
use Illuminate\Support\Facades\DB;

/**
 * Lo que cambia una carga familiar, se pida desde el expediente o desde el
 * Portal. Vivía en CargaFamiliarController; salió aquí cuando las solicitudes
 * del Portal tuvieron que aplicar lo mismo al validarse
 * (docs/diseno-cargas-familiares-portal.md, 7.1), para que la recuperación por
 * cédula esté en un solo sitio.
 */
final class CargaFamiliarService
{
    /**
     * Registra un familiar. Uno borrado se recupera con su misma fila, no se
     * crea otro: su historia clínica del Dispensario cuelga de ese id y se
     * numera con la cédula, que es única. Una fila nueva se quedaría sin
     * historia. Se recupera aunque fuera de otro servidor.
     *
     * @param array<string, mixed> $datos
     */
    public function registrar(int $servidorId, array $datos): CargaFamiliar
    {
        $datos = [...$datos, 'servidor_id' => $servidorId];

        $borrada = CargaFamiliar::onlyTrashed()
            ->where('cedula', $datos['cedula'])
            ->first();

        if (! $borrada) {
            return CargaFamiliar::create($datos);
        }

        DB::transaction(function () use ($borrada, $datos) {
            $borrada->restore();
            $borrada->update([...$datos, 'estado' => true]);
        });

        return $borrada;
    }

    /** ¿Hay una carga borrada con esta cédula, que se recuperaría al registrarla? */
    public function hayBorradaConCedula(string $cedula): bool
    {
        return CargaFamiliar::onlyTrashed()->where('cedula', $cedula)->exists();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(CargaFamiliar $carga, array $datos): CargaFamiliar
    {
        $carga->update($datos);

        return $carga;
    }

    public function alternarEstado(CargaFamiliar $carga): CargaFamiliar
    {
        $carga->update(['estado' => ! $carga->estado]);

        return $carga;
    }

    /**
     * Deja de ser carga sin borrarla: conserva su historia clínica, y Talento
     * Humano la puede volver a activar.
     */
    public function desactivar(CargaFamiliar $carga): CargaFamiliar
    {
        if ($carga->estado) {
            $carga->update(['estado' => false]);
        }

        return $carga;
    }
}
