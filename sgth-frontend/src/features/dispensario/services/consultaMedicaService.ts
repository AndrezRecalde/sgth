import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { DiagnosticoCie10 } from './cie10Service'

export interface DiagnosticoSecundario {
  id:                  number
  diagnostico_cie10_id: number
  diagnostico?:        DiagnosticoCie10
}

export interface ItemRecetaDetalle {
  id:                     number
  inventario_medicina_id: number
  cantidad_prescrita:     number
  cantidad_despachada:    number
  estado:                 string
  dosis:                  string
  frecuencia:             string
  duracion:               string
  observaciones?:         string | null
  inventario?: {
    nombre:         string
    presentacion:   string
    concentracion?: string | null
  }
}

export interface RecetaDetalle {
  id:                      number
  consulta_medica_id:      number
  fecha_emision:           string
  estado:                  string
  indicaciones_generales?: string | null
  items:                   ItemRecetaDetalle[]
}

export interface ConsultaMedica {
  id:                       number
  historia_clinica_id:      number
  agenda_medica_id?:        number | null
  /** De qué especialidad fue la atención. La consulta ya no depende del turno para saberlo. */
  especialidad?:            'medicina_general' | 'odontologia'
  medico_id:                number
  fecha_consulta:           string
  hora_consulta:            string
  tipo_atencion:            string
  tipo_diagnostico:         string
  motivo_consulta:          string
  enfermedad_actual?:       string | null
  examen_fisico?:           string | null
  diagnostico_detallado:    string
  diagnostico_cie10_id?:    number | null
  diagnostico_cie10_principal?: {
    id:          number
    codigo:      string
    descripcion: string
  } | null
  diagnosticos_secundarios?: DiagnosticoSecundario[]
  plan_tratamiento?:        string | null
  notas_medico?:            string | null
  medico?: {
    id: number
    nombre_completo?: string
  }
  /** Cuándo se registró: con esto se sabe si aún se puede corregir. */
  created_at?:              string
  recetas_medicas?: RecetaDetalle[]
}

/** Lo que la consulta decía antes de una corrección. */
export interface VersionConsulta {
  id:                     number
  tipo_atencion?:         string | null
  tipo_diagnostico?:      string | null
  motivo_consulta?:       string | null
  enfermedad_actual?:     string | null
  examen_fisico?:         string | null
  diagnostico_detallado?: string | null
  plan_tratamiento?:      string | null
  notas_medico?:          string | null
  diagnostico_cie10?: {
    codigo:      string
    descripcion: string
  } | null
  created_at:             string
  autor_del_cambio?: {
    id: number
    nombre_completo?: string
    usuario_ti?: string
  } | null
}

export interface CrearConsultaData {
  historia_clinica_id:       number
  agenda_medica_id?:         number | null
  fecha_consulta:            string
  hora_consulta:             string
  tipo_atencion:             string
  tipo_diagnostico:          string
  motivo_consulta:           string
  enfermedad_actual?:        string | null
  examen_fisico?:            string | null
  diagnostico_detallado:     string
  diagnostico_cie10_id?:     number | null
  diagnosticos_secundarios?: number[]
  plan_tratamiento?:         string | null
  notas_medico?:             string | null
}

export const consultaMedicaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<ConsultaMedica>>>(
      '/dispensario/consultas', { params }
    ).then(r => r.data.datos),

  crear: (data: CrearConsultaData) =>
    api.post<ApiResponse<ConsultaMedica>>(
      '/dispensario/consultas', data
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<ConsultaMedica>>(
      `/dispensario/consultas/${id}`
    ).then(r => r.data.datos),

  /** Lo que la consulta decía antes de cada corrección, de la más reciente. */
  versiones: (id: number) =>
    api.get<ApiResponse<VersionConsulta[]>>(
      `/dispensario/consultas/${id}/versiones`
    ).then(r => r.data.datos ?? []),

  /**
   * El historial de un paciente crece por años, así que va paginado y con
   * filtros de fecha. Antes pedía `per_page: 50` y tiraba la paginación: la
   * consulta 51 de un paciente antiguo no existía para la pantalla, sin que
   * nada lo dijera.
   */
  listarPorHistoria: (
    historiaClinicaId: number,
    params?: {
      page?:        number
      per_page?:    number
      fecha_desde?: string
      fecha_hasta?: string
    }
  ) =>
    api.get<ApiResponse<PaginatedResponse<ConsultaMedica>>>(
      '/dispensario/consultas',
      { params: {
        historia_clinica_id: historiaClinicaId,
        per_page: 10,
        ...params,
      }}
    ).then(r => ({
      consultas:    r.data.datos?.data ?? [],
      total:        r.data.datos?.total ?? 0,
      ultimaPagina: r.data.datos?.last_page ?? 1,
    })),

  actualizar: (id: number, data: Partial<CrearConsultaData>) =>
    api.patch<ApiResponse<ConsultaMedica>>(
      `/dispensario/consultas/${id}`, data
    ).then(r => r.data.datos),
}
