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
}

export const contextoConsultaService = {
  obtener: (historiaClinicaId: number, agendaMedicaId?: number) =>
    api.get<ApiResponse<ContextoConsulta>>(
      `/dispensario/historias-clinicas/${historiaClinicaId}/contexto-consulta`,
      { params: agendaMedicaId ? { agenda_medica_id: agendaMedicaId } : undefined }
    ).then(r => r.data.datos),
}
