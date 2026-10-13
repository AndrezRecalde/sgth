<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un equipo del inventario, con su custodio vigente y la unidad donde está.
 *
 * La unidad es la de quien lo tiene; la del propio equipo solo vale mientras
 * nadie lo tenga. El recurso la resuelve para que la pantalla no repita la
 * regla.
 *
 * Campo por campo: Scramble solo infiere la forma de un arreglo literal.
 *
 * @mixin \App\Models\InventarioTi\BienInformatico
 */
class BienInformaticoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $asignacion = $this->relationLoaded('asignacionVigente') ? $this->asignacionVigente : null;
        $custodio = $asignacion?->servidor;
        $unidad = $custodio ? $custodio->unidadAdministrativa : ($this->relationLoaded('unidadAdministrativa') ? $this->unidadAdministrativa : null);

        return [
            'id'                   => $this->id,
            'codigo_institucional' => $this->codigo_institucional,
            'codigo_anterior'      => $this->codigo_anterior,
            'codigo_qr'            => $this->codigo_qr,
            'numero_serie'         => $this->numero_serie,
            'modelo'               => $this->modelo,
            'tipo_bien_id'         => $this->tipo_bien_id,
            'subtipo_bien_id'      => $this->subtipo_bien_id,
            'marca_id'             => $this->marca_id,
            'origen_bien_id'       => $this->origen_bien_id,
            'ubicacion_id'         => $this->ubicacion_id,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'contrato_vigencia_id' => $this->contrato_vigencia_id,
            'bien_padre_id'        => $this->bien_padre_id,
            'estado_operativo'     => $this->estado_operativo?->value,
            'estado_etiqueta'      => $this->estado_operativo?->etiqueta(),
            'condicion_fisica'     => $this->condicion_fisica?->value,
            'fecha_compra'         => $this->fecha_compra?->toDateString(),
            'fecha_fin_vida_util'  => $this->fecha_fin_vida_util?->toDateString(),
            'vida_util_cumplida'   => $this->fecha_fin_vida_util !== null && $this->fecha_fin_vida_util->isPast(),
            'nombre_red'           => $this->nombre_red,
            'usuario_dominio'      => $this->usuario_dominio,
            'direccion_ip'         => $this->direccion_ip,
            'prestable'            => (bool) $this->prestable,
            'caracteristicas_tecnicas' => $this->caracteristicas_tecnicas,
            'observaciones'        => $this->observaciones,
            'componentes_count'    => $this->componentes_count ?? 0,

            'tipo'      => $this->whenLoaded('tipo', fn () => ['id' => $this->tipo->id, 'nombre' => $this->tipo->nombre]),
            'subtipo'   => $this->whenLoaded('subtipo', fn () => $this->subtipo ? ['id' => $this->subtipo->id, 'nombre' => $this->subtipo->nombre] : null),
            'marca'     => $this->whenLoaded('marca', fn () => ['id' => $this->marca->id, 'nombre' => $this->marca->nombre]),
            'ubicacion' => $this->whenLoaded('ubicacion', fn () => $this->ubicacion ? [
                'id' => $this->ubicacion->id, 'sede' => $this->ubicacion->sede,
                'piso' => $this->ubicacion->piso, 'nombre' => $this->ubicacion->nombre,
            ] : null),
            'contrato_vigencia' => $this->whenLoaded('contratoVigencia', fn () => $this->contratoVigencia ? [
                'id' => $this->contratoVigencia->id, 'proceso' => $this->contratoVigencia->proceso,
                'fecha_fin' => $this->contratoVigencia->fecha_fin?->toDateString(),
            ] : null),
            'origen' => $this->whenLoaded('origen', fn () => $this->origen ? [
                'id' => $this->origen->id, 'tipo_origen' => $this->origen->tipo_origen,
                'entidad_origen' => $this->origen->entidad_origen,
                'identificador_documento' => $this->origen->identificador_documento,
            ] : null),

            'custodio' => $custodio ? [
                'asignacion_id' => $asignacion->id,
                'desde'         => $asignacion->fecha_asignacion?->toDateString(),
                'servidor_id'   => $custodio->id,
                'cedula'        => $custodio->cedula,
                'nombre'        => trim("{$custodio->apellido} {$custodio->nombre}"),
            ] : null,
            'unidad' => $unidad ? ['id' => $unidad->id, 'nombre' => $unidad->nombre, 'acronimo' => $unidad->acronimo] : null,

            'padre' => $this->whenLoaded('padre', fn () => $this->padre ? [
                'id' => $this->padre->id, 'codigo_institucional' => $this->padre->codigo_institucional,
                'modelo' => $this->padre->modelo, 'tipo' => $this->padre->tipo?->nombre,
            ] : null),
            'componentes' => $this->whenLoaded('componentes', fn () => $this->componentes->map(fn ($c) => [
                'id' => $c->id, 'codigo_institucional' => $c->codigo_institucional,
                'modelo' => $c->modelo, 'tipo' => $c->tipo?->nombre,
                'estado_operativo' => $c->estado_operativo?->value,
            ])->all()),
            // La baja en curso o aprobada: la ficha avisa y no ofrece proponer otra.
            'baja' => $this->whenLoaded('bajaVigente', fn () => $this->bajaVigente ? [
                'id' => $this->bajaVigente->id, 'numero' => $this->bajaVigente->numero(),
                'estado' => $this->bajaVigente->estado->value, 'motivo' => $this->bajaVigente->motivo->etiqueta(),
                'fecha_informe' => $this->bajaVigente->fecha_informe?->toDateString(),
                'fecha_baja' => $this->bajaVigente->fecha_baja?->toDateString(),
            ] : null),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
