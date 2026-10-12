import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

/** Las causas de una licencia con remuneración (`CausaLicencia`, fase 5.1). */
export type CausaLicencia =
  | 'enfermedad'
  | 'enfermedad_catastrofica'
  | 'maternidad'
  | 'paternidad'
  | 'fallecimiento_de_la_madre'
  | 'adopcion'
  | 'hijos_hospitalizados'
  | 'calamidad_domestica'
  | 'matrimonio'

export type EstadoLicencia = 'pendiente' | 'aprobada' | 'anulada'

/** Una causa del catálogo, con su tope y los casos que lo cambian. */
export type CausaDelCatalogo = {
  codigo: CausaLicencia
  etiqueta: string
  base_legal: string
  explicacion: string
  /** Sin caso; null si no tiene tope (lo dice el certificado). */
  tope: number | null
  exige_alcance: boolean
  alcances: { codigo: string; etiqueta: string; tope: number | null }[]
}

export type Licencia = {
  id: number
  folio: string
  servidor_id: number
  servidor: { id: number; nombre: string | null; apellido: string | null; cedula: string | null; unidad: string | null } | null
  causa: CausaLicencia
  causa_etiqueta: string
  alcance: string | null
  alcance_etiqueta: string | null
  base_legal: string
  tope: number | null
  fecha_inicio: string
  fecha_fin: string
  dias: number
  observacion: string | null
  documento_nombre: string | null
  estado: EstadoLicencia
  registrada_en: string | null
  registrada_por: string | null
  aprobado_en: string | null
  aprobado_por: string | null
  /** 'sgth' si la escribió el SGTH en Sirha7; 'manual' si TH ya la había cargado. */
  registro_sirha7: 'sgth' | 'manual' | null
  nota_aprobacion: string | null
  sirha7_leave_nombre: string | null
  sirha7_dias_omitidos: { dia: string; motivo: string }[] | null
  anulado_en: string | null
  motivo_anulacion: string | null
  registrable_en_sirha7: boolean
  puede_aprobar: boolean
  puede_anular: boolean
}

/** Lo que se registrará en Sirha7: una licencia larga va en tramos de hasta 31 días. */
export type PreviaLicencia = {
  pendiente: boolean
  motivo: string | null
  registrable_en_sirha7: boolean
  motivo_sin_sirha7: string | null
  desde: string
  hasta: string
  jornada_completa: boolean
  tramos: { desde: string; hasta: string; referencia: string }[]
}

export type FiltrosLicencias = {
  page?: number
  per_page?: number
  estado?: EstadoLicencia
  causa?: CausaLicencia
  folio?: string
}

export type RegistrarLicenciaData = {
  servidor_id: number
  causa: CausaLicencia
  alcance?: string | null
  fecha_inicio: string
  fecha_fin: string
  observacion?: string | null
  archivo: File
}

const URL = '/asistencia/licencias'

export const licenciaService = {
  listar: (filtros?: FiltrosLicencias) =>
    api.get<ApiResponse<PaginatedResponse<Licencia>>>(URL, { params: filtros }).then((r) => r.data.datos),

  causas: () => api.get<ApiResponse<CausaDelCatalogo[]>>(`${URL}/causas`).then((r) => r.data.datos),

  registrar: (data: RegistrarLicenciaData) => {
    const datos = new FormData()
    datos.append('servidor_id', String(data.servidor_id))
    datos.append('causa', data.causa)
    if (data.alcance) datos.append('alcance', data.alcance)
    datos.append('fecha_inicio', data.fecha_inicio)
    datos.append('fecha_fin', data.fecha_fin)
    if (data.observacion) datos.append('observacion', data.observacion)
    datos.append('archivo', data.archivo)

    // La instancia de axios fija JSON: sin esto el PDF llegaba como texto.
    return api
      .post<ApiResponse<Licencia>>(URL, datos, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((r) => r.data.datos)
  },

  previa: (id: number) => api.get<ApiResponse<PreviaLicencia>>(`${URL}/${id}/sirha7`).then((r) => r.data.datos),

  aprobar: (id: number, leaveId: number) =>
    api.post<ApiResponse<Licencia>>(`${URL}/${id}/aprobar-sirha7`, { leave_id: leaveId }).then((r) => r.data.datos),

  aprobarSinSirha7: (id: number, nota: string) =>
    api.post<ApiResponse<Licencia>>(`${URL}/${id}/aprobar-sin-sirha7`, { nota }).then((r) => r.data.datos),

  anular: (id: number, motivo: string) =>
    api.post<ApiResponse<Licencia>>(`${URL}/${id}/anular`, { motivo }).then((r) => r.data.datos),

  documento: (id: number) =>
    api.get(`${URL}/${id}/documento`, { responseType: 'blob' }).then((r) => r.data as Blob),
}
