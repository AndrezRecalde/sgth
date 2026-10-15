<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\FormatoImpresionCredencial;
use App\Enums\VarianteIdentidad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * El PDF de la credencial (docs/diseno-comunicacion-social.md, 6.2; D7).
 *
 * CR80 vertical (54 × 85,6 mm). Dos salidas:
 *
 * - **CR80:** una hoja del tamaño de la tarjeta por cara, anverso y reverso
 *   seguidos, para la impresora de PVC. Es también la credencial digital que
 *   descarga el servidor.
 * - **A4:** nueve por cara, con el reverso en espejo por columnas para que,
 *   impreso a doble cara volteando por el borde largo, cada reverso caiga
 *   detrás de su anverso. Con guías de corte.
 *
 * Los logos son los vigentes hoy: la tarjeta se imprime ahora, no es un acto
 * con fecha (D3). V2 arriba en el anverso y V1 como marca de agua en el reverso.
 */
final class PdfCredencialService
{
    /** Milímetros de la tarjeta. */
    public const ANCHO = 54.0;
    public const ALTO = 85.6;

    /** La caja del logo del anverso, en milímetros. */
    private const LOGO_ANCHO = 42.0;
    private const LOGO_ALTO = 11.0;

    /** La rejilla de la hoja A4: columnas, filas y el aire entre tarjetas. */
    private const COLUMNAS = 3;
    private const FILAS = 3;
    private const SEPARACION = 4.0;

    private const PUNTOS_POR_MM = 72 / 25.4;

    public function __construct(private readonly IdentidadInstitucionalServiceInterface $identidad) {}

    /**
     * @param  Collection<int, array{datos: array<string, mixed>, foto: ?string, qr: ?string}>  $tarjetas
     */
    public function generar(Collection $tarjetas, FormatoImpresionCredencial $formato): string
    {
        $logo = $this->archivo(VarianteIdentidad::IMAGOTIPO_HORIZONTAL);
        $comun = [
            'logo'      => $logo,
            'logoMedidas' => $logo ? $this->encajar($logo, self::LOGO_ANCHO, self::LOGO_ALTO) : null,
            'isotipo'   => $this->archivo(VarianteIdentidad::ISOTIPO),
            'identidad' => $this->identidad->datos()->only(['nombre_oficial', 'siglas', 'nombre_corto', 'direccion', 'telefono', 'sitio_web']),
        ];

        if ($formato === FormatoImpresionCredencial::CR80) {
            return Pdf::loadView('pdf.comunicacion.credencial-cr80', [...$comun, 'tarjetas' => $tarjetas])
                ->setPaper([0, 0, self::ANCHO * self::PUNTOS_POR_MM, self::ALTO * self::PUNTOS_POR_MM])
                ->output();
        }

        return Pdf::loadView('pdf.comunicacion.credencial-a4', [
            ...$comun,
            'hojas'   => $this->hojas($tarjetas),
            'rejilla' => $this->rejilla(),
        ])->setPaper('a4', 'portrait')->output();
    }

    /**
     * De nueve en nueve. El reverso invierte las columnas de cada fila: al
     * voltear la hoja por el borde largo, la primera columna queda detrás de
     * la tercera.
     *
     * @return list<array{anverso: list<?array<string, mixed>>, reverso: list<?array<string, mixed>>}>
     */
    private function hojas(Collection $tarjetas): array
    {
        $porHoja = self::COLUMNAS * self::FILAS;

        return $tarjetas->chunk($porHoja)->map(function (Collection $grupo) use ($porHoja) {
            $anverso = array_pad($grupo->values()->all(), $porHoja, null);
            $reverso = [];

            foreach (array_chunk($anverso, self::COLUMNAS) as $fila) {
                array_push($reverso, ...array_reverse($fila));
            }

            return ['anverso' => $anverso, 'reverso' => $reverso];
        })->values()->all();
    }

    /**
     * Dónde va cada una de las nueve, en milímetros, centradas en la hoja.
     *
     * @return list<array{x: float, y: float}>
     */
    private function rejilla(): array
    {
        $anchoTotal = self::COLUMNAS * self::ANCHO + (self::COLUMNAS - 1) * self::SEPARACION;
        $altoTotal = self::FILAS * self::ALTO + (self::FILAS - 1) * self::SEPARACION;
        $x0 = (210 - $anchoTotal) / 2;
        $y0 = (297 - $altoTotal) / 2;
        $posiciones = [];

        for ($fila = 0; $fila < self::FILAS; $fila++) {
            for ($columna = 0; $columna < self::COLUMNAS; $columna++) {
                $posiciones[] = [
                    'x' => round($x0 + $columna * (self::ANCHO + self::SEPARACION), 2),
                    'y' => round($y0 + $fila * (self::ALTO + self::SEPARACION), 2),
                ];
            }
        }

        return $posiciones;
    }

    private function archivo(VarianteIdentidad $variante): ?string
    {
        $archivo = $this->identidad->archivo($variante);

        return $archivo !== null && is_file($archivo) ? $archivo : null;
    }

    /**
     * El tamaño del logo dentro de su caja sin deformarlo: dompdf no respeta
     * max-width y max-height en las imágenes.
     *
     * @return array{ancho: float, alto: float}
     */
    private function encajar(string $archivo, float $anchoMaximo, float $altoMaximo): array
    {
        $medidas = @getimagesize($archivo);

        if ($medidas === false || $medidas[0] <= 0 || $medidas[1] <= 0) {
            return ['ancho' => $anchoMaximo, 'alto' => $altoMaximo];
        }

        $escala = min($anchoMaximo / $medidas[0], $altoMaximo / $medidas[1]);

        return ['ancho' => round($medidas[0] * $escala, 2), 'alto' => round($medidas[1] * $escala, 2)];
    }
}
