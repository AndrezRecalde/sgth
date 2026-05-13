<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
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

    public function obtenerFichaTecnicaCompleta(array $filtros): array
    {
        // Retorna un mock estructurado con el bien y sus relaciones
        return [
            'bien' => null,
            'asignaciones' => [],
            'mantenimientos' => []
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
            $bien->update(['estado' => 'dado_de_baja']);

            // Simula generar acta en PDF y guardado en SGD
            return [
                'bien_informatico_id' => $bien->id,
                'acta_pdf' => '/storage/actas/baja_' . $bien->id . '.pdf',
                'sgd_referencia' => 'SGD-BAJA-' . time()
            ];
        });
    }
}