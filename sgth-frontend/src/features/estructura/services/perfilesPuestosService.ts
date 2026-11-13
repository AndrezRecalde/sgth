import api from '@/lib/axios'
import type { ApiResponse, NivelCompetencia } from '@/types/api'
import type { TipoPuesto } from '../constants/clasificacionPuesto'
import type { ContenidoPuesto } from './manualPuestosService'

/**
 * Copiar el perfil de otro puesto y el tablero de completitud
 * (docs/diseno-manual-de-puestos.md, P6, PR 15). El backend arma estas
 * respuestas a mano, sin recurso, así que los tipos van aquí.
 */

/** Las competencias que pasan del nivel del grado del otro puesto al de este (D5). */
export type AjusteDeNivel = { de: NivelCompetencia; a: NivelCompetencia; competencias: number }

export type VistaPreviaCopia = {
  origen: { id: number; cargo: string | null; unidad: string | null; grado: string | null; mision: string | null }
  /** Lo que se copia: solo las actividades activas y las competencias vigentes. */
  copia: ContenidoPuesto
  omitidas: { actividades: number; competencias: number }
  ajuste_de_nivel: AjusteDeNivel | null
  /** Lo que este puesto tiene y se reemplaza. */
  reemplaza: ContenidoPuesto
  /** Por qué no se puede copiar, o `null`. */
  impedimento: string | null
}

export type PerfilCopiado = {
  mision: boolean
  requisitos: number
  actividades: number
  competencias: number
  ajuste_de_nivel: AjusteDeNivel | null
}

export type ParteDelPerfil = 'mision' | 'requisitos' | 'actividades' | 'competencias'

/** Un puesto activo en el tablero, con lo que el cajón de su perfil necesita. */
export type CompletitudPuesto = ContenidoPuesto & {
  id: number
  tipo: TipoPuesto
  cargo: { nombre: string } | null
  unidad_administrativa: { id: number; nombre: string } | null
  grupo_ocupacional: { grupo: string | null; grado_codigo: string | null; nivel_competencia_sugerido: NivelCompetencia | null } | null
  regimen_laboral: string | null
  nivel_complejidad: string | null
  ocupado: boolean
  faltan: ParteDelPerfil[]
  completo: boolean
}

export type CompletitudPerfiles = {
  puestos: CompletitudPuesto[]
  resumen: {
    total: number
    completos: number
    incompletos: number
    ocupados_incompletos: number
  } & Record<`sin_${ParteDelPerfil}`, number>
}

const copia = (puestoId: number) => `/estructura/puestos/${puestoId}/perfil/copia`

export const perfilesPuestosService = {
  vistaPrevia: (puestoId: number, origenId: number) =>
    api.get<ApiResponse<VistaPreviaCopia>>(copia(puestoId), { params: { origen_id: origenId } }).then((r) => r.data.datos),

  /** Devuelve lo copiado y el mensaje que lo resume. */
  copiar: (puestoId: number, origenId: number) =>
    api.post<ApiResponse<PerfilCopiado>>(copia(puestoId), { origen_id: origenId }).then((r) => r.data),

  completitud: () =>
    api.get<ApiResponse<CompletitudPerfiles>>('/estructura/completitud-de-perfiles').then((r) => r.data.datos),
}
