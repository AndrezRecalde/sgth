import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { OrdenExamen } from './examenService'

export type VistaBandeja = 'pendientes' | 'vencidas'

/** Una orden de la bandeja: con el paciente y quién la pidió, que la fila enseña. */
export interface OrdenBandeja extends OrdenExamen {
  historia_clinica?: {
    id:             number
    servidor?:      { nombre: string; apellido: string; cedula: string | null } | null
    carga_familiar?: { nombres: string; apellidos: string; cedula: string | null } | null
  } | null
  solicitante?: {
    id:        number
    servidor?: { nombre: string; apellido: string } | null
  } | null
}

export interface ResumenBandeja {
  pendientes: number
  vencidas:   number
}

export interface FiltrosBandeja {
  vista:     VistaBandeja
  buscar?:   string
  page:      number
  per_page?: number
}

export const bandejaExamenesService = {
  /** Las órdenes que esperan resultados, las más antiguas primero. */
  listar: (filtros: FiltrosBandeja) =>
    api.get<ApiResponse<PaginatedResponse<OrdenBandeja>, { resumen: ResumenBandeja }>>(
      '/dispensario/examenes/bandeja',
      { params: { ...filtros, buscar: filtros.buscar || undefined } },
    ).then(r => ({ pagina: r.data.datos, resumen: r.data.meta?.resumen })),
}

/** El nombre y la cédula del paciente, sea servidor o carga familiar. */
export function pacienteDeOrden(o: OrdenBandeja): { nombre: string; cedula: string | null } {
  const servidor = o.historia_clinica?.servidor
  if (servidor) return { nombre: `${servidor.nombre} ${servidor.apellido}`, cedula: servidor.cedula }
  const familiar = o.historia_clinica?.carga_familiar
  if (familiar) return { nombre: `${familiar.nombres} ${familiar.apellidos}`, cedula: familiar.cedula }
  return { nombre: '—', cedula: null }
}
