<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\BienInformaticoServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\InventarioTi\BienInformatico;
use App\Services\InventarioTi\EtiquetaQrService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Las etiquetas con QR para pegar en los equipos (fase 1.6): la de uno, desde
 * su ficha, o las del filtro del listado en hojas A4.
 */
final class EtiquetaBienController extends Controller
{
    public function __construct(
        private readonly EtiquetaQrService $etiquetas,
        private readonly BienInformaticoServiceInterface $bienes,
    ) {}

    public function una(BienInformatico $bien): Response
    {
        return $this->pdf($this->etiquetas->generar(collect([$bien->load('tipo:id,nombre', 'marca:id,nombre')])));
    }

    public function lote(Request $request): Response
    {
        return $this->pdf($this->etiquetas->generar(
            $this->bienes->paraEtiquetas($request->query(), EtiquetaQrService::MAXIMO)
        ));
    }

    /** @param  array{content: string, filename: string}  $pdf */
    private function pdf(array $pdf): Response
    {
        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf['filename'] . '"',
        ]);
    }
}
