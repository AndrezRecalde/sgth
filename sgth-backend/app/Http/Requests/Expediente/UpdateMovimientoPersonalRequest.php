<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Edición de una acción de personal en borrador. No incluye 'tipo_movimiento'
 * ni 'subtipo_movimiento' a propósito: cambiar la naturaleza del acto no es
 * editarlo — para eso se anula el borrador y se registra el correcto, porque
 * de lo contrario las validaciones de elegibilidad que corrieron al crearlo
 * quedarían sin aplicar.
 */
class UpdateMovimientoPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion'       => 'sometimes|string|max:1000',
            'fecha_efectiva'    => 'sometimes|date',
            'fecha_inicio'      => 'nullable|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio', // una licencia de un día empieza y termina el mismo día
            'unidad_origen_id'  => 'nullable|exists:unidades_administrativas,id',
            'unidad_destino_id' => 'nullable|exists:unidades_administrativas,id',
            // Comisiones e intercambio: la entidad del Estado a la que va (fase 2.3).
            'institucion_destino' => 'nullable|string|max:255',
            'para_estudios_o_eventos' => 'nullable|boolean',
            // La sentencia de una restitución (fase 2.6). Que sean obligatorios
            // en ella lo decide RestitucionService.
            'informe_uath_numero'   => 'nullable|string|max:100',
            // El supuesto del provisional y el acta del concurso (fase 3.2).
            'supuesto_provisional_propuesto' => 'nullable|string|in:suspendido_o_destituido,puesto_vacante,nivel_jerarquico_superior,de_prueba',
            'acta_concurso_numero'  => 'nullable|string|max:100',
            'acta_concurso_fecha'   => 'nullable|date|before_or_equal:today',
            'causa_judicial_numero' => 'nullable|string|max:100',
            'unidad_judicial'       => 'nullable|string|max:255',
            'fecha_sentencia'       => 'nullable|date',
            'fecha_ejecutoria'      => 'nullable|date|after_or_equal:fecha_sentencia',
            'remuneraciones_dejadas_de_percibir' => 'nullable|numeric|min:0|max:9999999999',
            'puesto_origen_id'  => 'nullable|exists:puestos,id',
            'puesto_destino_id' => 'nullable|exists:puestos,id',

            'tipo_nombramiento_propuesto' => ['nullable', new Enum(TipoNombramiento::class)],
            'remuneracion_propuesta'      => 'nullable|numeric|min:0',
            // Sin comparación contra fecha_efectiva: esta edición es parcial y
            // ese campo puede no venir en la petición.
            'fecha_fin_propuesta'         => 'nullable|date',
            'numero_contrato'             => 'nullable|string|max:100',
            'partida_presupuestaria_id'   => 'nullable|exists:partidas_presupuestarias,id',
            'puede_marcar'                => 'nullable|boolean',
            // Corregir a quién cubre un reemplazo mal enlazado. Las reglas se
            // vuelven a evaluar en el servicio: aceptar el cambio sin
            // revalidar dejaría entrar aquí lo que se rechaza al crear.
            'cubre_movimiento_id'         => 'nullable|integer|exists:movimientos_personal,id',

            'requiere_dictamen_medico' => 'nullable|boolean',
            'resolucion_numero'        => 'nullable|string|max:100',
            'observacion'              => 'nullable|string|max:1000',
            'codigo'                   => 'nullable|string|max:30',
            'lugar_trabajo'            => 'nullable|string|max:255',
            'caucionado'               => 'nullable|boolean',
            'caucion_numero'           => 'nullable|string|max:100',
            'caucion_fecha'            => 'nullable|date',
        ];
    }
}
