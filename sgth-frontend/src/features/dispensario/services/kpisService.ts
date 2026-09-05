import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type Especialidad = 'medicina_general' | 'odontologia'

export interface KpisDispensario {
  atenciones_mes_actual: number
  atenciones_por_especialidad: Record<Especialidad, number>
  pacientes_por_tipo: {
    titulares:     number
    beneficiarios: number
  }
  top_diagnosticos: Array<{
    codigo:       string
    descripcion:  string
    especialidad: Especialidad
    total:        number
  }>
  medicamentos_mas_despachados: Array<{
    nombre:           string
    total_despachado: number
  }>
  recetas_estado: {
    pendiente:            number
    despachada_parcial:   number
    despachada_completa:  number
    anulada:              number
  }
  consultas_por_medico: Array<{
    medico:          string
    especialidad:    Especialidad
    total_consultas: number
  }>
  alertas_inventario: {
    medicamentos_bajo_stock: Array<{
      nombre:       string
      stock_actual: number
      stock_minimo: number
    }>
    medicamentos_por_caducar: Array<{
      nombre:          string
      lote:            string
      stock:           number
      fecha_caducidad: string
      dias_restantes:  number
    }>
  }
}

export const kpisService = {
  obtener: () =>
    api.get<ApiResponse<KpisDispensario>>('/dispensario/dashboard/kpis')
      .then(r => r.data.datos),
}

export const ETIQUETA_ESPECIALIDAD: Record<Especialidad, string> = {
  medicina_general: 'Medicina general',
  odontologia:      'Odontología',
}
