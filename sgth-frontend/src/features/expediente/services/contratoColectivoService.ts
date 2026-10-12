import api from '@/lib/axios'
import type { ApiResponse, EstadoAccionPersonal } from '@/types/api'

/**
 * El contrato colectivo de los obreros (fase 4.6): el documento, su vigencia,
 * el dictamen del Ministerio de Finanzas y las cláusulas que cambian cálculos.
 * Los decimales llegan como cadena, como los manda Postgres.
 */
export type ContratoColectivo = {
  id: number
  numero: string
  fecha_suscripcion: string | null
  fecha_registro_mdt: string | null
  vigencia_desde: string
  vigencia_hasta: string | null
  dictamen_finanzas_numero: string | null
  dictamen_finanzas_fecha: string | null
  dias_adicionales_vacaciones: number
  bonificacion_jubilacion_sbu: string | null
  bonificacion_retiro_sbu: string | null
  incremento_pactado_porcentaje: string | null
  documento_nombre: string | null
  observacion: string | null
  tiene_documento: boolean
  /** Sin el dictamen, el contrato se tiene por inexistente y sus cláusulas no se aplican. */
  tiene_dictamen: boolean
}

export type SalarioBasico = {
  id: number
  anio: number
  monto: string
  acuerdo: string | null
}

export type ContratoColectivoResumen = {
  /** El que rige hoy con el dictamen de Finanzas; null si rige la ley sola. */
  vigente: ContratoColectivo | null
  contratos: ContratoColectivo[]
  salarios_basicos: SalarioBasico[]
}

export type ContratoColectivoData = {
  numero: string
  fecha_suscripcion?: string | null
  fecha_registro_mdt?: string | null
  vigencia_desde: string
  vigencia_hasta?: string | null
  dictamen_finanzas_numero?: string | null
  dictamen_finanzas_fecha?: string | null
  dias_adicionales_vacaciones?: number | null
  bonificacion_jubilacion_sbu?: number | null
  bonificacion_retiro_sbu?: number | null
  incremento_pactado_porcentaje?: number | null
  observacion?: string | null
  archivo?: File | null
}

export type SalarioBasicoData = { anio: number; monto: number; acuerdo?: string | null }

/** Un obrero en la vista previa del incremento en lote. */
export type FilaDelIncremento = {
  servidor_id: number
  nombre: string
  cedula: string | null
  unidad: string | null
  puesto: string | null
  remuneracion_actual: number | null
  remuneracion_nueva: number | null
  /** Solo en los omitidos: por qué no recibe el incremento. */
  motivo?: string
}

export type IncrementoEnLoteData = {
  modo: 'porcentaje' | 'monto'
  valor: number
  unidad_administrativa_id?: number | null
  por_contrato_colectivo?: boolean
  fecha_efectiva?: string
  descripcion?: string
  documento_origen?: string
  dictamen_presupuestario_ref?: string
}

export type LoteAccion = {
  id: number
  tipo: 'incremento_obreros'
  descripcion: string
  fecha_efectiva: string
  documento_origen: string
  dictamen_presupuestario_ref: string
  parametros: { modo: 'porcentaje' | 'monto'; valor: number; unidad_administrativa_id: number | null }
  contrato_colectivo_id: number | null
  creado_por: string | null
  created_at: string | null
  total: number
  /** Cuántos de sus actos hay en cada estado. */
  por_estado: Partial<Record<EstadoAccionPersonal, number>>
}

export type ActoDelLote = {
  movimiento_id: number
  codigo_registro: string | null
  estado: EstadoAccionPersonal
  nombre: string
  cedula: string | null
  remuneracion_actual: number | null
  remuneracion_nueva: number | null
}

export type LoteAccionDetalle = LoteAccion & { actos: ActoDelLote[] }

export type ResultadoDelLote = {
  registrados: number
  fallidos: { movimiento_id: number; nombre: string; mensaje: string }[]
  lote: LoteAccionDetalle
}

const URL = '/expediente/contratos-colectivos'

function formulario(data: ContratoColectivoData): FormData {
  const datos = new FormData()

  for (const [campo, valor] of Object.entries(data)) {
    if (campo === 'archivo' || valor === null || valor === undefined || valor === '') continue
    datos.append(campo, String(valor))
  }
  if (data.archivo) datos.append('archivo', data.archivo)

  return datos
}

// La instancia de axios fija JSON: sin esto el PDF llegaba como texto.
const multipart = { headers: { 'Content-Type': 'multipart/form-data' } }

export const contratoColectivoService = {
  resumen: () =>
    api.get<ApiResponse<ContratoColectivoResumen>>(URL).then((r) => r.data.datos),

  guardar: (data: ContratoColectivoData, id?: number) =>
    api
      .post<ApiResponse<ContratoColectivo>>(id ? `${URL}/${id}` : URL, formulario(data), multipart)
      .then((r) => r.data.datos),

  descargar: (id: number) =>
    api.get(`${URL}/${id}/documento`, { responseType: 'blob' }).then((r) => r.data as Blob),

  guardarSalarioBasico: (data: SalarioBasicoData) =>
    api.post<ApiResponse<SalarioBasico>>('/expediente/salarios-basicos', data).then((r) => r.data.datos),
}

export const loteAccionService = {
  listar: () =>
    api.get<ApiResponse<LoteAccion[]>>('/expediente/lotes-accion').then((r) => r.data.datos),

  detalle: (id: number) =>
    api.get<ApiResponse<LoteAccionDetalle>>(`/expediente/lotes-accion/${id}`).then((r) => r.data.datos),

  previsualizar: (data: IncrementoEnLoteData) =>
    api
      .post<ApiResponse<{ incluidos: FilaDelIncremento[]; omitidos: FilaDelIncremento[] }>>(
        '/expediente/lotes-accion/incremento-obreros/previsualizar', data,
      )
      .then((r) => r.data.datos),

  preparar: (data: IncrementoEnLoteData) =>
    api
      .post<ApiResponse<LoteAccionDetalle>>('/expediente/lotes-accion/incremento-obreros', data)
      .then((r) => r.data.datos),

  registrar: (id: number) =>
    api.post<ApiResponse<ResultadoDelLote>>(`/expediente/lotes-accion/${id}/registrar`).then((r) => r.data),

  descartar: (id: number, motivo: string) =>
    api
      .post<ApiResponse<{ anulados: number; lote: LoteAccionDetalle }>>(`/expediente/lotes-accion/${id}/descartar`, { motivo })
      .then((r) => r.data),
}
