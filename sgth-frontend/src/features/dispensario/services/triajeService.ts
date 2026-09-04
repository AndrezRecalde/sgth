import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { AgendaMedica } from './agendaService'
import type { NivelAlerta } from '../constants/signosVitales'

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
  /** Lo calcula el backend al registrar: es el nivel con el que se valoró. */
  nivel_alerta?:            NivelAlerta | null
  hallazgos_alerta?:        {
    constante: string
    etiqueta:  string
    valor:     number
    nivel:     NivelAlerta
  }[] | null
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

  ultimoPorAgenda: (agendaId: number) =>
    api.get<ApiResponse<Triaje | null>>(
      `/dispensario/agenda/${agendaId}/triaje/ultimo`
    ).then(r => r.data.datos),

  /** Todas las tomas del turno, de la más antigua a la más reciente. */
  historial: (agendaId: number) =>
    api.get<ApiResponse<Triaje[]>>(
      `/dispensario/agenda/${agendaId}/triaje/historial`
    ).then(r => r.data.datos),

  registrar: (agendaId: number, data: CrearTriajeData) =>
    api.post<ApiResponse<Triaje>>(
      `/dispensario/agenda/${agendaId}/triaje`, data
    ).then(r => r.data.datos),
}
