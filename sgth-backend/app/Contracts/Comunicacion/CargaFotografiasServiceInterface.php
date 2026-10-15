<?php

namespace App\Contracts\Comunicacion;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * La carga masiva de fotos con la cédula en el nombre del archivo
 * (docs/diseno-comunicacion-social.md, 5.1).
 *
 * La simulación recibe solo los nombres, así que se puede revisar un lote de
 * cientos de fotos sin subir ninguna. La carga recibe los archivos por tandas
 * y vuelve a comprobarlo todo: no confía en la simulación.
 */
interface CargaFotografiasServiceInterface
{
    /**
     * Qué pasaría con cada archivo.
     *
     * @param  list<string>  $nombres
     * @return array{resumen: array{total: int, se_cargan: int, se_omiten: int}, archivos: list<array<string, mixed>>}
     */
    public function simular(array $nombres, bool $reemplazar): array;

    /**
     * Carga una tanda y dice qué pasó con cada archivo.
     *
     * @param  list<UploadedFile>  $archivos
     * @return array{resumen: array{total: int, se_cargan: int, se_omiten: int}, archivos: list<array<string, mixed>>}
     */
    public function cargar(array $archivos, bool $reemplazar, User $usuario): array;
}
