import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { Triaje } from './triajeService'

export interface ConsultaResumen {
  id:               number
  fecha_consulta:   string
  diagnostico_detallado: string
  medico?: { nombre_completo?: string }
}

export interface Alergia {
  id:           number
  tipo:         string
  descripcion:  string
  severidad:    string
  observacion?: string | null
}

export interface Antecedente {
  id:                number
  tipo:              string
  descripcion:       string
  fecha_aproximada?: number | null
}

/**
 * La discapacidad y la enfermedad catastrófica que constan en el Expediente,
 * del servidor o del familiar. `null` para quien no tiene expediente (un
 * candidato de ingreso). El grado lo deriva el backend del porcentaje.
 */
export interface CondicionDeclarada {
  discapacidades: {
    etiqueta:   string
    porcentaje: number | null
    grado:      string | null
  }[]
  enfermedades: {
    nombre:       string
    codigo_cie10: string | null
  }[]
  /** Marcado a mano antes de que existieran los registros, sin detalle. */
  discapacidad_sin_detalle: boolean
  enfermedad_sin_detalle:   boolean
}

export interface ContextoConsulta {
  historia_clinica: {
    id: number
    servidor?: { id: number; nombre: string; apellido: string } | null
    carga_familiar?: {
      id: number; nombres: string; apellidos: string
    } | null
    alergias: Alergia[]
    antecedentes: Antecedente[]
  }
  triaje_actual:        Triaje | null
  consultas_anteriores: ConsultaResumen[]
  condicion_declarada:  CondicionDeclarada | null
}

export const contextoConsultaService = {
  obtener: (historiaClinicaId: number, agendaMedicaId?: number) =>
    api.get<ApiResponse<ContextoConsulta>>(
      `/dispensario/historias-clinicas/${historiaClinicaId}/contexto-consulta`,
      { params: agendaMedicaId ? { agenda_medica_id: agendaMedicaId } : undefined }
    ).then(r => r.data.datos),
}
