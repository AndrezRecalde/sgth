<?php

namespace App\Http\Resources\Comunicacion;

use App\Services\Comunicacion\DatosCredencial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un servidor en la pantalla Credenciales: quién es, si tiene foto, su
 * credencial viva y si la tarjeta impresa sigue al día.
 *
 * `situacion` resume lo que le toca hacer a Comunicación con él:
 * - `sin_foto`: no tiene credencial porque le falta la foto.
 * - `pendiente`: tiene foto pero la credencial aún no se creó; la crea la
 *   tarea de la noche, o subir o restaurar la foto.
 * - `sin_imprimir`, `desactualizada`, `al_dia`: según su última impresión.
 * - `vencida`: pasó la fecha de fin del contrato.
 * - `por_recibir`: ya no trabaja aquí y su tarjeta impresa no ha vuelto; solo
 *   sale con ese filtro.
 *
 * @mixin \App\Models\Expediente\Servidor
 */
class ServidorCredencialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $credencial = $this->credencialViva;
        $impresion = $credencial?->ultimaImpresion;
        $cambios = $credencial ? DatosCredencial::cambios($impresion, DatosCredencial::de($this->resource, $credencial)) : [];

        return [
            'id'              => $this->id,
            'cedula'          => $this->cedula,
            'nombre_completo' => $this->nombre_completo,
            'unidad'          => $this->unidadAdministrativa ? [
                'id'     => $this->unidadAdministrativa->id,
                'nombre' => $this->unidadAdministrativa->nombre,
            ] : null,
            'cargo'           => $this->puesto?->cargo?->nombre,
            'fotografia'      => $this->fotografiaVigente ? ['id' => $this->fotografiaVigente->id] : null,
            /** @var 'sin_foto'|'pendiente'|'vencida'|'sin_imprimir'|'desactualizada'|'al_dia'|'por_recibir' */
            'situacion'       => match (true) {
                ! $this->estado                                             => 'por_recibir',
                $credencial === null && $this->fotografiaVigente === null => 'sin_foto',
                $credencial === null                                       => 'pendiente',
                ! $credencial->estaVigente()                               => 'vencida',
                $impresion === null                                        => 'sin_imprimir',
                $cambios !== []                                            => 'desactualizada',
                default                                                    => 'al_dia',
            },
            'credencial'      => $credencial ? [
                'id'                      => $credencial->id,
                'estado'                  => $credencial->estado->value,
                'estado_etiqueta'         => $credencial->estado->etiqueta(),
                'vigente'                 => $credencial->estaVigente(),
                'valida_hasta'            => $credencial->valida_hasta?->toDateString(),
                'impresiones'             => (int) ($credencial->impresiones_count ?? 0),
                'ultima_impresion'        => $impresion ? new ImpresionCredencialResource($impresion) : null,
                /** @var list<string> */
                'cambios_desde_impresion' => $cambios,
            ] : null,
        ];
    }
}
