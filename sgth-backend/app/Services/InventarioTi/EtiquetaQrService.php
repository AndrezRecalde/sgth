<?php

namespace App\Services\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * La etiqueta que se pega al equipo (docs/diseno-subsistema-tic.md, fase 1.6):
 * el código institucional en grande y un QR que abre su ficha en el sistema.
 *
 * Hasta aquí `codigo_qr` era un texto (`001774-QR`) y no había imagen. El QR
 * lleva la dirección de la ficha y no solo el código: escaneado con el
 * teléfono de un técnico, abre el equipo; el código va también escrito, para
 * quien no tenga lector.
 *
 * Una etiqueta sola sale en su tamaño (70 × 40 mm); varias, en hojas A4 de
 * 3 × 8, para imprimirlas en lote.
 */
final class EtiquetaQrService
{
    /** Cuántas etiquetas se imprimen de una vez: 10 hojas A4. */
    public const MAXIMO = 240;

    /** 70 × 40 mm en puntos, para la etiqueta suelta. */
    private const TAMANO_SUELTA = [0, 0, 198.43, 113.39];

    /**
     * @param  Collection<int, BienInformatico>  $bienes
     * @return array{content: string, filename: string}
     */
    public function generar(Collection $bienes): array
    {
        $suelta = $bienes->count() === 1;

        $etiquetas = $bienes->map(fn (BienInformatico $b) => [
            'codigo'  => $b->codigo_institucional,
            'detalle' => collect([$b->tipo?->nombre, $b->marca?->nombre, $b->modelo])->filter()->implode(' · '),
            'serie'   => $b->numero_serie,
            'qr'      => $this->qr($this->urlFicha($b)),
        ]);

        $pdf = Pdf::loadView('pdf.inventario.etiquetas', ['etiquetas' => $etiquetas, 'suelta' => $suelta])
            ->setPaper($suelta ? self::TAMANO_SUELTA : 'a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => $suelta ? "etiqueta-{$bienes->first()->codigo_institucional}.pdf" : 'etiquetas-equipos.pdf',
        ];
    }

    public function urlFicha(BienInformatico $bien): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . "/tic/inventario/equipos/{$bien->id}";
    }

    /** Un QR que falla no impide la etiqueta: el código va escrito igual. */
    private function qr(string $url): ?string
    {
        try {
            $svg = QrCode::format('svg')->size(110)->margin(1)->errorCorrection('M')->generate($url);

            return $svg ? 'data:image/svg+xml;base64,' . base64_encode((string) $svg) : null;
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar el QR de la etiqueta', ['url' => $url, 'motivo' => $e->getMessage()]);

            return null;
        }
    }
}
