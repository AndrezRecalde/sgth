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
  informe_uath_numero: '',
  causa_judicial_numero: '',
  unidad_judicial: '',
  fecha_sentencia: null,
  fecha_ejecutoria: null,
  remuneraciones_dejadas_de_percibir: null,
  fecha_providencia: null,
  orden_judicial: 'providencia',
  resolucion_numero: '',
  base_legal: '',
  acto_origen_tipo: null,
  acto_origen_numero: '',
  acto_origen_fecha: null,
  observacion: '',
  caucionado: false,
  caucion_numero: '',
  caucion_fecha: null,
  requiere_dictamen_medico: false,
  tipo_nombramiento_propuesto: null,
  supuesto_provisional_propuesto: null,
  acta_concurso_numero: '',
  acta_concurso_fecha: null,
  // El término para presentarse del ingreso (fase 6.4).
  dias_para_presentarse: 3,
  razon_dias_adicionales: '',
  credencial_cne_numero: '',
  credencial_cne_fecha: null,
  con_remuneracion: null,
  prorroga_causa: null,
  fecha_consentimiento: null,
  fecha_presentacion_renuncia: null,
  acta_posesion_numero: '',
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
    informe_uath_numero: movimiento.informe_uath_numero ?? '',
    causa_judicial_numero: movimiento.causa_judicial_numero ?? '',
    unidad_judicial: movimiento.unidad_judicial ?? '',
    fecha_sentencia: soloFecha(movimiento.fecha_sentencia),
    fecha_ejecutoria: soloFecha(movimiento.fecha_ejecutoria),
    remuneraciones_dejadas_de_percibir: movimiento.remuneraciones_dejadas_de_percibir != null
      ? Number(movimiento.remuneraciones_dejadas_de_percibir) : null,
    // El reintegro del obrero: con la sentencia, si la tiene (fase 4.5b).
    fecha_providencia: soloFecha(movimiento.fecha_providencia),
    orden_judicial: movimiento.fecha_sentencia ? 'sentencia' : 'providencia',
    tipo_nombramiento_propuesto: movimiento.tipo_nombramiento_propuesto ?? null,
    supuesto_provisional_propuesto: movimiento.supuesto_provisional_propuesto ?? null,
    acta_concurso_numero: movimiento.acta_concurso_numero ?? '',
    acta_concurso_fecha: soloFecha(movimiento.acta_concurso_fecha),
    dias_para_presentarse: movimiento.dias_para_presentarse ?? 3,
    razon_dias_adicionales: movimiento.razon_dias_adicionales ?? '',
    credencial_cne_numero: movimiento.credencial_cne_numero ?? '',
    credencial_cne_fecha: soloFecha(movimiento.credencial_cne_fecha),
    con_remuneracion: movimiento.con_remuneracion ?? null,
    prorroga_causa: movimiento.prorroga_causa ?? null,
    fecha_consentimiento: soloFecha(movimiento.fecha_consentimiento),
    fecha_presentacion_renuncia: soloFecha(movimiento.fecha_presentacion_renuncia),
    acta_posesion_numero: movimiento.acta_posesion_numero ?? '',
    numero_contrato: movimiento.numero_contrato ?? '',
    fecha_fin_propuesta: soloFecha(movimiento.fecha_fin_propuesta),
    puede_marcar: movimiento.puede_marcar ?? false,
    cubre_movimiento_id: movimiento.cubre_movimiento_id ?? null,
    requiere_dictamen_medico: movimiento.requiere_dictamen_medico ?? false,
    resolucion_numero: movimiento.resolucion_numero ?? '',
    base_legal: movimiento.base_legal ?? '',
    acto_origen_tipo: movimiento.acto_origen_tipo ?? null,
    acto_origen_numero: movimiento.acto_origen_numero ?? '',
    acto_origen_fecha: soloFecha(movimiento.acto_origen_fecha),
    observacion: movimiento.observacion ?? '',
    caucionado: movimiento.caucionado ?? false,
    caucion_numero: movimiento.caucion_numero ?? '',
    caucion_fecha: soloFecha(movimiento.caucion_fecha),
  }
}
