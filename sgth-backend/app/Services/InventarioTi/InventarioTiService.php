<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\MantenimientoBien;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface
{
    /**
     * Lo que queda aquí es el historial, la auditoría física y la baja: se
     * rehacen en sus fases (1.5, 1.6). La ficha del equipo se mudó a
     * `BienInformaticoService` (1.1) y la custodia a `CustodiaService` (1.3).
     */

    /**
     * La vida del bien: quién lo ha tenido y qué se le ha hecho.
     *
     * Devolvía un molde vacío —`bien` a null y las dos listas sin nada— y el
     * controlador ni siquiera tenía el método que la ruta declaraba, así que
     * `bienes/{id}/historial` respondía con un 500. Los modelos y sus tablas
     * estaban completos desde el principio.
     *
     * @return array{bien: BienInformatico, asignaciones: mixed, mantenimientos: mixed}
     */
    public function obtenerFichaTecnicaCompleta(array $filtros): array
    {
        $bien = BienInformatico::with(['tipo', 'marca', 'origen'])
            ->findOrFail($filtros['bien_informatico_id']);

        return [
            'bien' => $bien,
            // De la más reciente a la más antigua: lo que interesa de un
            // historial es dónde está el bien ahora y de dónde viene.
            'asignaciones' => AsignacionBien::with([
                'servidor:id,nombre,apellido,cedula', 'usuario:id,nombre,apellido,cedula',
                'anterior:id,servidor_id', 'anterior.servidor:id,nombre,apellido,cedula',
                'registradaPor:id,usuario_ti,email,servidor_id', 'registradaPor.servidor:id,nombre,apellido',
            ])
                ->where('bien_informatico_id', $bien->id)
                ->orderByDesc('fecha_asignacion')
                ->orderByDesc('id')
                ->get(),
            // Con los anulados: el historial no esconde lo que se corrigió. Los
            // sin fecha (del Excel de TIC) van al final.
            'mantenimientos' => MantenimientoBien::with([
                'tecnico:id,usuario_ti,email,servidor_id', 'tecnico.servidor:id,nombre,apellido',
                'contratoVigencia:id,proceso,proveedor',
                'registradoPor:id,usuario_ti,email,servidor_id', 'registradoPor.servidor:id,nombre,apellido',
                'anuladoPor:id,usuario_ti,email,servidor_id', 'anuladoPor.servidor:id,nombre,apellido',
            ])
                ->where('bien_informatico_id', $bien->id)
                ->orderByRaw('fecha_mantenimiento DESC NULLS LAST')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function registrarAuditoriaFisica(array $datos)
    {
        // Retorna mock de registro de auditoria
        return ['estado' => 'Auditoría registrada'];
    }

    public function procesarBaja(array $datos)
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::findOrFail($datos['bien_informatico_id']);

            // La columna es `estado_operativo`. Escribía `estado`, que no
            // existe en la tabla ni en el `fillable`, así que Eloquent lo
            // descartaba en silencio: la baja respondía que sí y el bien se
            // quedaba activo.
            $bien->update(['estado_operativo' => EstadoBien::DADO_DE_BAJA]);

            return [
                'bien_informatico_id' => $bien->id,
                'estado_operativo'    => $bien->estado_operativo->value,
                'motivo'              => $datos['motivo'],
                // El acta en PDF y su archivo en el SGD no están construidos.
                // Antes se devolvía una ruta y una referencia inventadas, que
                // no llevaban a ningún sitio; es preferible decir que faltan.
                'acta_pendiente'      => true,
            ];
        });
    }
}