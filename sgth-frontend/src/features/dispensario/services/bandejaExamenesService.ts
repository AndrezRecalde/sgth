import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { OrdenExamen } from './examenService'
import type { PersonaResultado, ResultadoMedico } from './resultadoMedicoService'

export type VistaBandeja = 'pendientes' | 'vencidas' | 'por-revisar'

/** El paciente tal como llega a la bandeja: servidor o carga familiar. */
interface PacienteBandeja {
  id:              number
  servidor?:       { nombre: string; apellido: string; cedula: string | null } | null
  carga_familiar?: { nombres: string; apellidos: string; cedula: string | null } | null
}

/** Una orden de la bandeja: con el paciente y quién la pidió, que la fila enseña. */
export interface OrdenBandeja extends OrdenExamen {
  historia_clinica?: PacienteBandeja | null
  solicitante?:      PersonaResultado | null
}

/** Un resultado por revisar: con el paciente y quién responde por él. */
export interface ResultadoPorRevisar extends ResultadoMedico {
  created_at:        string
  historia_clinica?: (PacienteBandeja & { servidor_id: number | null; carga_familiar_id: number | null }) | null
  orden_examen?:     (NonNullable<ResultadoMedico['orden_examen']> & { solicitante?: PersonaResultado | null }) | null
  consulta_medica?:  { id: number; fecha_consulta: string; medico?: PersonaResultado | null } | null
}

export interface ResumenBandeja {
  pendientes:  number
  vencidas:    number
  por_revisar: number
}

export interface FiltrosBandeja {
  vista:     'pendientes' | 'vencidas'
  buscar?:   string
  page:      number
  per_page?: number
}

export interface FiltrosPorRevisar {
  /** El médico que responde por ellos: pidió la orden o, sin orden, atendió la consulta. */
  solicitante?: number
  buscar?:      string
  page:         number
  per_page?:    number
}

export const bandejaExamenesService = {
  /** Las órdenes que esperan resultados, las más antiguas primero. */
  listar: (filtros: FiltrosBandeja) =>
    api.get<ApiResponse<PaginatedResponse<OrdenBandeja>, { resumen: ResumenBandeja }>>(
      '/dispensario/examenes/bandeja',
      { params: { ...filtros, buscar: filtros.buscar || undefined } },
    ).then(r => ({ pagina: r.data.datos, resumen: r.data.meta?.resumen })),

  /** Los resultados que nadie ha leído, los más antiguos primero (D3). */
  porRevisar: (filtros: FiltrosPorRevisar) =>
    api.get<ApiResponse<PaginatedResponse<ResultadoPorRevisar>>>(
      '/dispensario/examenes/bandeja/por-revisar',
      { params: { ...filtros, buscar: filtros.buscar || undefined } },
    ).then(r => r.data.datos),
}

/** El nombre y la cédula del paciente, sea servidor o carga familiar. */
export function pacienteDeOrden(o: { historia_clinica?: PacienteBandeja | null }): { nombre: string; cedula: string | null } {
  const servidor = o.historia_clinica?.servidor
  if (servidor) return { nombre: `${servidor.nombre} ${servidor.apellido}`, cedula: servidor.cedula }
  const familiar = o.historia_clinica?.carga_familiar
  if (familiar) return { nombre: `${familiar.nombres} ${familiar.apellidos}`, cedula: familiar.cedula }
  return { nombre: '—', cedula: null }
}
