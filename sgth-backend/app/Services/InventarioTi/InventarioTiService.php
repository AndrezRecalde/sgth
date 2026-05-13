<?php
namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Support\Facades\DB;

final class InventarioTiService implements InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico
    {
        return DB::transaction(function () use ($datos) {
            // Generación de QR (Mock de QrService)
            $datos['codigo_qr'] = $datos['codigo_institucional'] . '-QR';
            return BienInformatico::create($datos);
        });
    }

    public function asignarBien(array $datos): AsignacionBien
    {
        return DB::transaction(function () use ($datos) {
            $asignacion = AsignacionBien::create($datos);
            // Acta de entrega-recepción (Mock de PdfService)
            $asignacion->update(['url_acta_pdf' => '/storage/actas/acta_entrega_' . $asignacion->id . '.pdf']);
            return $asignacion;
        });
    }
}
