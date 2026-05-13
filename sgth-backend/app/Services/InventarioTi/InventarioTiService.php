<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\OrigenBien;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface {
    public function registrarBien(array $datos): BienInformatico {
        return DB::transaction(function () use ($datos) {
            // Calcular vida útil si tenemos el origen y el tipo
            if (!empty($datos['origen_bien_id']) && !empty($datos['tipo_bien_id'])) {
                $origen = OrigenBien::find($datos['origen_bien_id']);
                $tipo = TipoBien::find($datos['tipo_bien_id']);
                if ($origen && $tipo && $tipo->anios_vida_util > 0) {
                    $datos['fecha_fin_vida_util'] = $origen->fecha_adquisicion->copy()->addYears($tipo->anios_vida_util);
                }
            }
            // Generación de QR
            $datos['codigo_qr'] = $datos['codigo_institucional'] . '-QR';
            return BienInformatico::create($datos);
        });
    }
    public function asignarBien(array $datos): AsignacionBien {
        return DB::transaction(function () use ($datos) {
            $asignacion = AsignacionBien::create($datos);
            $asignacion->update(['url_acta_pdf' => '/storage/actas/acta_entrega_' . $asignacion->id . '.pdf']);
            return $asignacion;
        });
    }
}