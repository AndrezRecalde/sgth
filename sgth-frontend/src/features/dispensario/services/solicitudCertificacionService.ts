import api from '@/lib/axios'
import type { SemanticTone } from '@/config/design.tokens'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface SolicitudConstantesVitales {
  id?:                       number
  peso_kg?:                  number | null
  talla_cm?:                 number | null
  imc?:                      number | null
  temperatura_c?:            number | null
  presion_sistolica?:        number | null
  presion_diastolica?:       number | null
  frecuencia_cardiaca?:      number | null
  frecuencia_respiratoria?:  number | null
  saturacion_oxigeno?:       number | null
  glucosa?:                  number | null
  observaciones_enfermera?:  string | null
  registrado_en?:            string | null
}

export interface CrearSolicitudSignosVitalesData {
  peso_kg:                  number
  talla_cm:                 number
  temperatura_c:            number
  presion_sistolica:        number
  presion_diastolica:       number
  frecuencia_cardiaca:      number
  frecuencia_respiratoria:  number
  saturacion_oxigeno:       number
  glucosa?:                 number | null
  observaciones_enfermera?: string | null
}


/** Puesto tal como lo expone el detalle de la solicitud. */
export interface PuestoDeSolicitud {
  id: number
  cargo?: {
    id?: number
    nombre: string
    /** Código CIUO-08 del cargo. La ficha FEMO lo hereda, no lo pide. */
    codigo_ciuo?: string | null
  } | null
  unidad_administrativa?: {
    id: number
    nombre: string
  } | null
}


/**
 * Sexo del paciente, para decidir qué bloque reproductivo mostrar.
 *
 * Puede faltar: la columna `genero` no está poblada para toda la plantilla,
 * así que el formulario tiene que comportarse bien cuando llega vacía.
 */
export type SexoPaciente = 'masculino' | 'femenino' | 'otro' | null

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
  ficha_femo_id?:      number | null
  dictamen?:           string | null
  observacion_medica?: string | null
  constantes_vitales?: SolicitudConstantesVitales | null
  servidor?: {
    id:      number
    nombre:  string
    apellido: string
    cedula:  string
    genero?: SexoPaciente
    tipo_sangre?: string | null
    puesto?: PuestoDeSolicitud | null
    unidad_administrativa?: {
      id:     number
      nombre: string
    } | null
  } | null
  postulante?: {
    id:       number
    nombres:  string
    apellidos: string
    cedula:   string
    correo:   string
    genero?: SexoPaciente
    tipo_sangre?: string | null
    /** En reclutamiento express el puesto lo trae el aspirante, no la convocatoria. */
    puesto?: PuestoDeSolicitud | null
  } | null
  convocatoria?: {
    id:     number
    codigo: string
    titulo: string
    puesto?: PuestoDeSolicitud | null
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

export const TONO_ESTADO_SOLICITUD: Record<string, SemanticTone> = {
  pendiente:   'warning',
  en_proceso:  'info',
  completada:  'success',
  cancelada:   'danger',
}

/**
 * El dictamen del Dispensario. «Apto con restricciones» es una advertencia,
 * no un fallo: el candidato entra, pero con condiciones que alguien debe leer.
 */
export const TONO_DICTAMEN: Record<string, SemanticTone> = {
  apto:                   'success',
  apto_con_restricciones: 'warning',
  no_apto:                'danger',
}

export const DICTAMEN_LABELS: Record<string, string> = {
  apto:                   'Apto',
  apto_con_restricciones: 'Apto c/restricciones',
  no_apto:                'No apto',
}

export const ESTADO_SOLICITUD_LABELS: Record<string, string> = {
  pendiente:   'Pendiente',
  en_proceso:  'En proceso',
  completada:  'Completada',
  cancelada:   'Cancelada',
}

export interface CrearSolicitudLoteData {
  servidor_ids:   number[]
  tipo_evento:    'periodica' | 'reintegro' | 'retiro'
  fecha_limite?:  string | null
  observaciones?: string | null
}

export interface SolicitudLoteOmitida {
  servidor_id: number
  motivo:      string
}

export interface SolicitudLoteResultado {
  creadas:  SolicitudCertificacion[]
  omitidas: SolicitudLoteOmitida[]
}

export const solicitudCertificacionService = {
  listar: (params?: {
    estado?:      string
    tipo_evento?: string
    servidor_id?: number
    origen?:      string
    unidad_administrativa_id?: number
    anio?:        number
    per_page?:    number
  }) =>
    api.get<ApiResponse<PaginatedResponse<SolicitudCertificacion>>>(
      '/dispensario/solicitudes-certificacion',
      { params }
    ).then(r => r.data.datos),

  crearLote: (data: CrearSolicitudLoteData) =>
    api.post<ApiResponse<SolicitudLoteResultado>>(
      '/dispensario/solicitudes-certificacion/lote', data
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

  pendientesTriaje: () =>
    api.get<ApiResponse<SolicitudCertificacion[]>>(
      '/dispensario/solicitudes-certificacion/pendientes-triaje'
    ).then(r => r.data.datos),

  registrarSignosVitales: (id: number, data: CrearSolicitudSignosVitalesData) =>
    api.post<ApiResponse<SolicitudConstantesVitales>>(
      `/dispensario/solicitudes-certificacion/${id}/signos-vitales`, data
    ).then(r => r.data.datos),
}
