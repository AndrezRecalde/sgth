<?php

namespace App\Http\Requests\Expediente;

use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMovimientoPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_movimiento'   => ['required', new Enum(TipoMovimientoPersonal::class)],
            // La correspondencia tipo ↔ subtipo (obligatoriedad incluida) la
            // resuelve MovimientoPersonalService::resolverSubtipo(), que da un
            // mensaje de negocio con los subtipos válidos del tipo elegido.
            // Aquí solo se comprueba que el valor exista en el enum.
            'subtipo_movimiento' => ['nullable', new Enum(SubtipoMovimientoPersonal::class)],
            // Editable por Talento Humano; si no viene, el servicio aplica el
            // default del tipo/subtipo.
            'requiere_dictamen_medico' => ['nullable', 'boolean'],
            'descripcion'       => 'required|string|max:1000',
            'fecha_efectiva'    => 'required|date',
            'fecha_inicio'      => 'nullable|date',
            'fecha_fin'         => 'nullable|date|after:fecha_inicio',
            'unidad_origen_id'  => 'nullable|exists:unidades_administrativas,id',
            'unidad_destino_id' => 'nullable|exists:unidades_administrativas,id',
            'puesto_origen_id'  => 'nullable|exists:puestos,id',
            'puesto_destino_id' => 'nullable|exists:puestos,id',
            // "Datos propuestos" de MovimientoPersonal (ver migración
            // agregar_datos_propuestos_a_movimientos): solo obligatorios para
            // 'ingreso' (creaVinculo()), que es el único tipo con formulario
            // hoy. MovimientoPersonalStateService::validarDatosPropuestos()
            // los exige igual al transicionar a 'registrada'.
            'tipo_nombramiento_propuesto' => ['nullable', 'required_if:tipo_movimiento,ingreso', new Enum(TipoNombramiento::class)],
            // La remuneración ya no se exige al crear: en Código del Trabajo y
            // Servicios Profesionales se negocia en el contrato y no se deriva
            // del puesto. Se pide al aprobar, junto al resto de datos del
            // vínculo (ver MovimientoPersonalStateService::aplicarRegistro()).
            'remuneracion_propuesta'      => ['nullable', 'numeric', 'min:0'],
            'fecha_fin_propuesta'         => ['nullable', 'date'],
            'partida_presupuestaria_id'   => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            // Datos de la contratación. Solo tienen sentido en el ingreso, pero
            // se aceptan aquí porque es el formulario el que decide mostrarlos:
            // el servicio no los usa cuando el tipo no crea vínculo.
            'numero_contrato'             => ['nullable', 'string', 'max:100'],
            'puede_marcar'                => ['nullable', 'boolean'],
            // Encadena un Ingreso y Vinculación con la Cesación de Funciones
            // que lo habilitó. La coherencia (mismo servidor, que sea cesación
            // y que esté registrada) la valida el servicio.
            'movimiento_previo_id' => 'nullable|integer|exists:movimientos_personal,id',
            // Enlace de reemplazo: la comisión o licencia cuyo hueco cubre este
            // ingreso. Las reglas (que sea ausencia, que esté registrada, que no
            // esté ya cubierta y que el plazo no la exceda) las valida el
            // servicio en validarReemplazo().
            'cubre_movimiento_id' => 'nullable|integer|exists:movimientos_personal,id',
            'resolucion_numero' => 'nullable|string|max:100',
            'observacion'       => 'nullable|string|max:1000',
            'codigo'            => 'nullable|string|max:30',
            'lugar_trabajo'     => 'nullable|string|max:255',
            'caucionado'        => 'nullable|boolean',
            'caucion_numero'    => 'nullable|string|max:100',
            'caucion_fecha'     => 'nullable|date',
        ];
    }
}
