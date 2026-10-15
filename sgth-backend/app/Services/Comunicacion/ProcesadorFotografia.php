<?php

namespace App\Services\Comunicacion;

use App\Exceptions\ReglaNegocioException;
use GdImage;

/**
 * Convierte la foto que llega en las dos que se guardan
 * (docs/diseno-comunicacion-social.md, 5.1).
 *
 * - Endereza la foto según la orientación EXIF. Una cámara guarda la foto
 *   vertical acostada y anota cómo girarla; GD no lo lee solo.
 * - Recorta a 3:4. La subida individual ya llega recortada a mano y aquí no
 *   cambia; la carga masiva se recorta centrada, algo cargada hacia arriba,
 *   donde suele estar la cara.
 * - Guarda una versión de 600×800 y una miniatura cuadrada de 160×160 tomada
 *   de la parte de arriba, para los avatares.
 * - Las vuelve a codificar en JPEG. El original no se guarda: puede pesar
 *   10 MB y trae metadatos, entre ellos la ubicación, que así se pierden.
 */
final class ProcesadorFotografia
{
    public const ANCHO = 600;
    public const ALTO = 800;
    public const LADO_MINIATURA = 160;

    /** El recorte 3:4 tiene que medir al menos esto, o se vería pixelado. */
    public const ANCHO_MINIMO = 300;
    public const ALTO_MINIMO = 400;

    /** Una foto mayor no la manda ninguna cámara de escritorio. */
    private const PIXELES_MAXIMOS = 50_000_000;

    private const CALIDAD_JPEG = 85;

    /** @return array{completa: string, miniatura: string} los dos JPEG */
    public function procesar(string $ruta): array
    {
        $info = @getimagesize($ruta);
        $tipos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if ($info === false || ! in_array($info[2], $tipos, true)) {
            throw new ReglaNegocioException('El archivo no es una imagen JPG, PNG o WebP que se pueda leer.');
        }

        [$ancho, $alto, $tipo] = $info;

        if ($ancho * $alto > self::PIXELES_MAXIMOS) {
            throw new ReglaNegocioException('La imagen es demasiado grande: hasta 50 megapíxeles.');
        }

        $this->asegurarMemoria($ancho, $alto);

        $imagen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
        };

        if (! $imagen instanceof GdImage) {
            throw new ReglaNegocioException('El archivo no es una imagen JPG, PNG o WebP que se pueda leer.');
        }

        if ($tipo === IMAGETYPE_JPEG) {
            $imagen = $this->enderezar($imagen, $ruta);
        }

        [$x, $y, $anchoRecorte, $altoRecorte] = $this->recorte(imagesx($imagen), imagesy($imagen));

        if ($anchoRecorte < self::ANCHO_MINIMO || $altoRecorte < self::ALTO_MINIMO) {
            throw new ReglaNegocioException(sprintf(
                'La foto es demasiado pequeña: recortada a 3:4 debe medir al menos %d × %d píxeles.',
                self::ANCHO_MINIMO, self::ALTO_MINIMO,
            ));
        }

        $completa = $this->lienzo(self::ANCHO, self::ALTO);
        imagecopyresampled($completa, $imagen, 0, 0, $x, $y, self::ANCHO, self::ALTO, $anchoRecorte, $altoRecorte);
        imagedestroy($imagen);

        // El cuadrado de arriba: en una foto de credencial, la cara.
        $sobrante = self::ALTO - self::ANCHO;
        $miniatura = $this->lienzo(self::LADO_MINIATURA, self::LADO_MINIATURA);
        imagecopyresampled(
            $miniatura, $completa, 0, 0, 0, (int) round($sobrante * 0.1),
            self::LADO_MINIATURA, self::LADO_MINIATURA, self::ANCHO, self::ANCHO,
        );

        return ['completa' => $this->jpeg($completa), 'miniatura' => $this->jpeg($miniatura)];
    }

    /**
     * El mayor recorte 3:4 que cabe. Si sobra ancho, se centra; si sobra alto,
     * se toma más de arriba que de abajo (30 % / 70 %), para no cortar la cabeza.
     *
     * @return array{int, int, int, int} x, y, ancho y alto del recorte
     */
    private function recorte(int $ancho, int $alto): array
    {
        if ($ancho * 4 > $alto * 3) {
            $anchoRecorte = intdiv($alto * 3, 4);

            return [intdiv($ancho - $anchoRecorte, 2), 0, $anchoRecorte, $alto];
        }

        $altoRecorte = intdiv($ancho * 4, 3);

        return [0, (int) round(($alto - $altoRecorte) * 0.3), $ancho, $altoRecorte];
    }

    /** Aplica la orientación EXIF, si la foto la trae. */
    private function enderezar(GdImage $imagen, string $ruta): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($ruta) : false;
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // imagerotate gira en sentido antihorario: -90 es un cuarto a la derecha.
        $girada = match ($orientacion) {
            3, 4    => imagerotate($imagen, 180, 0),
            5, 6    => imagerotate($imagen, -90, 0),
            7, 8    => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($girada, IMG_FLIP_HORIZONTAL);
        }

        if ($girada !== $imagen) {
            imagedestroy($imagen);
        }

        return $girada;
    }

    /** Un lienzo blanco: un PNG con transparencia queda sobre fondo blanco. */
    private function lienzo(int $ancho, int $alto): GdImage
    {
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        return $lienzo;
    }

    private function jpeg(GdImage $imagen): string
    {
        ob_start();
        imagejpeg($imagen, null, self::CALIDAD_JPEG);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /**
     * GD descomprime la foto entera en memoria: una de 24 megapíxeles ocupa
     * unos 100 MB. Se sube el límite solo si hace falta y nunca se baja, porque
     * en la suite de pruebas corre sin límite y bajarlo la haría caer.
     */
    private function asegurarMemoria(int $ancho, int $alto): void
    {
        $limite = $this->enBytes((string) ini_get('memory_limit'));
        if ($limite < 0) {
            return;
        }

        // Cuatro bytes por píxel, la copia girada y un margen para el resto.
        $necesario = memory_get_usage() + $ancho * $alto * 4 * 2 + 64 * 1024 * 1024;
        if ($necesario > $limite) {
            ini_set('memory_limit', (string) $necesario);
        }
    }

    private function enBytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return -1;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g'     => $numero * 1024 ** 3,
            'm'     => $numero * 1024 ** 2,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }
}
