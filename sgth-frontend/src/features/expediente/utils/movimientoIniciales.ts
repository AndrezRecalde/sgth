import type { DefaultValues } from 'react-hook-form'
import type { MovimientoPersonal } from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/** Valores de un formulario nuevo. */
export const VALORES_EN_BLANCO: DefaultValues<MovimientoFormData> = {
  causal: null,
  descripcion: '',
  fecha_efectiva: '',
  fecha_inicio: null,
  fecha_fin: null,
  unidad_destino_id: null,
  puesto_destino_id: null,
  remuneracion_propuesta: null,
  partida_presupuestaria_id: null,
  lugar_trabajo: '',
  institucion_destino: '',
  para_estudios_o_eventos: false,
  resolucion_numero: '',
  observacion: '',
  caucionado: false,
  caucion_numero: '',
  caucion_fecha: null,
  requiere_dictamen_medico: false,
  tipo_nombramiento_propuesto: null,
  numero_contrato: '',
  fecha_fin_propuesta: null,
  puede_marcar: false,
  cubre_movimiento_id: null,
}

/**
 * Los valores con los que abre el formulario al corregir un borrador.
 *
 * Las fechas llegan del API como ISO y los controles las quieren en
 * `YYYY-MM-DD`; los importes, como cadena decimal de Postgres. Va aparte del
 * componente porque es traducción de formas, no interfaz.
 *
 * Solo se llama con borradores que el formulario corrige
 * (`editable_en_formulario`), que son los que tienen una clase del catálogo.
 */
export function valoresDelBorrador(
  movimiento: MovimientoPersonal,
): DefaultValues<MovimientoFormData> {
  const soloFecha = (v?: string | null) => v?.split('T')[0] ?? null

  return {
    clase: movimiento.clase ?? undefined,
    causal: movimiento.causal ?? null,
    descripcion: movimiento.descripcion ?? '',
    fecha_efectiva: soloFecha(movimiento.fecha_efectiva) ?? '',
    fecha_inicio: soloFecha(movimiento.fecha_inicio),
    fecha_fin: soloFecha(movimiento.fecha_fin),
    unidad_destino_id: movimiento.unidad_destino_id ?? null,
    puesto_destino_id: movimiento.puesto_destino_id ?? null,
    remuneracion_propuesta: movimiento.remuneracion_propuesta != null
      ? Number(movimiento.remuneracion_propuesta) : null,
    partida_presupuestaria_id: movimiento.partida_presupuestaria_id ?? null,
    lugar_trabajo: movimiento.lugar_trabajo ?? '',
    institucion_destino: movimiento.institucion_destino ?? '',
    para_estudios_o_eventos: movimiento.para_estudios_o_eventos ?? false,
    tipo_nombramiento_propuesto: movimiento.tipo_nombramiento_propuesto ?? null,
    numero_contrato: movimiento.numero_contrato ?? '',
    fecha_fin_propuesta: soloFecha(movimiento.fecha_fin_propuesta),
    puede_marcar: movimiento.puede_marcar ?? false,
    cubre_movimiento_id: movimiento.cubre_movimiento_id ?? null,
    requiere_dictamen_medico: movimiento.requiere_dictamen_medico ?? false,
    resolucion_numero: movimiento.resolucion_numero ?? '',
    observacion: movimiento.observacion ?? '',
    caucionado: movimiento.caucionado ?? false,
    caucion_numero: movimiento.caucion_numero ?? '',
    caucion_fecha: soloFecha(movimiento.caucion_fecha),
  }
}
