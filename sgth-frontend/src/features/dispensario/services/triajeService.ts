import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { AgendaMedica } from './agendaService'

export interface Triaje {
  id:                       number
  agenda_medica_id:         number
  historia_clinica_id?:     number | null
  enfermera_id:             number
  peso_kg:                  number
  talla_cm:                 number
  imc?:                     number | null
  temperatura_c:            number
  presion_sistolica:        number
  presion_diastolica:       number
  frecuencia_cardiaca:      number
  frecuencia_respiratoria:  number
  saturacion_oxigeno:       number
  glucosa?:                 number | null
  observaciones_enfermera?: string | null
  registrado_en?:           string | null
}

export interface CrearTriajeData {
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

export const triajeService = {
  pendientes: () =>
    api.get<ApiResponse<AgendaMedica[]>>(
      '/dispensario/triaje/pendientes'
    ).then(r => r.data.datos),

  obtener: (agendaId: number) =>
    api.get<ApiResponse<Triaje>>(
      `/dispensario/agenda/${agendaId}/triaje`
    ).then(r => r.data.datos),

  registrar: (agendaId: number, data: CrearTriajeData) =>
    api.post<ApiResponse<Triaje>>(
      `/dispensario/agenda/${agendaId}/triaje`, data
    ).then(r => r.data.datos),
}
