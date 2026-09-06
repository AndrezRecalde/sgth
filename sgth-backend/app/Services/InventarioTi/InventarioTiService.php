<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\OrigenBien;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico
    {
        return DB::transaction(function () use ($datos) {
            // Calcular vida útil si tenemos el origen y el tipo
            if (!empty($datos['origen_bien_id']) && !empty($datos['tipo_bien_id'])) {
                $origen = OrigenBien::find($datos['origen_bien_id']);
                $tipo = TipoBien::find($datos['tipo_bien_id']);
                if ($origen && $tipo && $tipo->anios_vida_util > 0) {
                    $datos['fecha_fin_vida_util'] = \Carbon\Carbon::parse($origen->fecha_adquisicion)->addYears($tipo->anios_vida_util);
                }
            }
            // Generación de QR
            $datos['codigo_qr'] = $datos['codigo_institucional'] . '-QR';
            return BienInformatico::create($datos);
        });
    }
    public function asignarBien(array $datos): AsignacionBien
    {
        return DB::transaction(function () use ($datos) {
            $asignacion = AsignacionBien::create($datos);
            $asignacion->update(['url_acta_pdf' => '/storage/actas/acta_entrega_' . $asignacion->id . '.pdf']);
            return $asignacion;
        });
    }

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
            'asignaciones' => AsignacionBien::with('servidor:id,nombre,apellido,cedula')
                ->where('bien_informatico_id', $bien->id)
                ->orderByDesc('fecha_asignacion')
                ->get(),
            'mantenimientos' => MantenimientoBien::with('tecnico:id,usuario_ti,servidor_id')
                ->where('bien_informatico_id', $bien->id)
                ->orderByDesc('fecha_mantenimiento')
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
            $bien->update(['estado_operativo' => 'dado_de_baja']);

            return [
                'bien_informatico_id' => $bien->id,
                'estado_operativo'    => $bien->estado_operativo,
                'motivo'              => $datos['motivo'],
                // El acta en PDF y su archivo en el SGD no están construidos.
                // Antes se devolvía una ruta y una referencia inventadas, que
                // no llevaban a ningún sitio; es preferible decir que faltan.
                'acta_pendiente'      => true,
            ];
        });
    }
}