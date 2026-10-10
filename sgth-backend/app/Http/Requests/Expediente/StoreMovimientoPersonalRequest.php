<?php

namespace App\Http\Requests\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreMovimientoPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `causal` ya tiene nombre en `lang/es/validation.php`, pero es el del visto
     * bueno («causal del Art. 172 del Código del Trabajo»): aquí sería falso.
     */
    public function attributes(): array
    {
        return [
            'clase'  => 'tipo de acción de personal',
            'causal' => 'causal de la acción',
            'institucion_destino' => 'institución de destino',
        ];
    }

    public function rules(): array
    {
        return [
            // La acción se pide por su clase legal. Solo las que se crean desde
            // este formulario: la subrogación y el encargo nacen en su pantalla,
            // y la bitácora del expediente no es un acto. Antes la API aceptaba
            // los dieciocho tipos del enum, así que se podía crear por aquí una
            // subrogación sin su fila en `subrogaciones` o una «novedad de
            // contrato» que nacía registrada y sin efecto.
            'clase' => ['required', Rule::in(array_map(
                fn (ClaseAccionPersonal $c) => $c->value,
                array_filter(ClaseAccionPersonal::cases(), fn (ClaseAccionPersonal $c) => $c->seCreaDesdeElFormulario())
            ))],
            // Que la causal sea obligatoria, que pertenezca a la clase y que
            // aplique al nombramiento lo decide
            // MovimientoPersonalService::registrarPorClase(), con un mensaje que
            // nombra las causales válidas.
            'causal' => ['nullable', 'string', 'max:40'],
            // Editable por Talento Humano; si no viene, el servicio aplica el
            // default del tipo/subtipo.
            'requiere_dictamen_medico' => ['nullable', 'boolean'],
            'descripcion'       => 'required|string|max:1000',
            'fecha_efectiva'    => 'required|date',
            'fecha_inicio'      => 'nullable|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio', // una licencia de un día empieza y termina el mismo día
            'unidad_origen_id'  => 'nullable|exists:unidades_administrativas,id',
            'unidad_destino_id' => 'nullable|exists:unidades_administrativas,id',
            // Comisiones e intercambio: la entidad del Estado a la que va (fase 2.3).
            'institucion_destino' => 'nullable|string|max:255',
            'para_estudios_o_eventos' => 'nullable|boolean',
            'puesto_origen_id'  => 'nullable|exists:puestos,id',
            'puesto_destino_id' => 'nullable|exists:puestos,id',
            // "Datos propuestos" de MovimientoPersonal (ver migración
            // agregar_datos_propuestos_a_movimientos): solo obligatorios para
            // 'ingreso' (creaVinculo()), que es el único tipo con formulario
            // hoy. MovimientoPersonalStateService::validarDatosPropuestos()
            // los exige igual al transicionar a 'registrada'.
            'tipo_nombramiento_propuesto' => ['nullable', 'required_if:clase,ingreso', new Enum(TipoNombramiento::class)],
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
