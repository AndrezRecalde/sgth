import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface SolicitudCertificacion {
  id:                number
  tipo_evento:       string
  origen:            string
  cedula_paciente:   string
  nombres_paciente:  string
  correo_paciente?:  string | null
  puesto_solicitado?: string | null
  estado:            string
  fecha_limite?:     string | null
  created_at:        string
  dictamen?:           string | null
  observacion_medica?: string | null
  servidor?: {
    id:      number
    nombre:  string
    apellido: string
    cedula:  string
  } | null
  postulante?: {
    id:       number
    nombres:  string
    apellidos: string
    cedula:   string
    correo:   string
  } | null
  convocatoria?: {
    id:     number
    codigo: string
    titulo: string
  } | null
  solicitado_por?: {
    servidor?: {
      nombre:   string
      apellido: string
    } | null
  } | null
}

export const TIPO_EVENTO_OPTIONS = [
  { value: 'ingreso',    label: 'Ingreso / Pre-ocupacional' },
  { value: 'reintegro',  label: 'Reintegro'                 },
  { value: 'periodica',  label: 'Periódica'                 },
  { value: 'retiro',     label: 'Retiro'                    },
  { value: 'especial',   label: 'Especial'                  },
]

export const ESTADO_SOLICITUD_COLORS: Record<string, string> = {
  pendiente:   'orange',
  en_proceso:  'blue',
  completada:  'emerald',
  cancelada:   'red',
}

export const ESTADO_SOLICITUD_LABELS: Record<string, string> = {
  pendiente:   'Pendiente',
  en_proceso:  'En proceso',
  completada:  'Completada',
  cancelada:   'Cancelada',
}

export const solicitudCertificacionService = {
  listar: (params?: {
    estado?:      string
    tipo_evento?: string
    per_page?:    number
  }) =>
    api.get<ApiResponse<PaginatedResponse<SolicitudCertificacion>>>(
      '/dispensario/solicitudes-certificacion',
      { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}`
    ).then(r => r.data.datos),

  iniciarProceso: (id: number) =>
    api.patch<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}/iniciar`
    ).then(r => r.data.datos),

  completar: (
    id: number,
    data: {
      dictamen:           'apto' | 'apto_con_restricciones' | 'no_apto'
      observacion_medica?: string | null
      ficha_femo_id?:     number | null
    }
  ) =>
    api.patch<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}/completar`,
      data
    ).then(r => r.data.datos),

  confirmarIncorporacion: (id: number) =>
    api.post<ApiResponse<{ servidor_id: number }>>(
      `/dispensario/solicitudes-certificacion/${id}/confirmar-incorporacion`
    ).then(r => r.data.datos),
}
