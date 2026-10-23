import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { CatalogoExamen } from './examenService'

export type ExamenDeRiesgo = Pick<CatalogoExamen, 'id' | 'nombre' | 'area' | 'orden' | 'sensible' | 'activo'>

/** Un factor de riesgo del MSP con los exámenes que pide (D6). */
export interface RiesgoConExamenes {
  categoria: string
  factor:    string
  examenes:  ExamenDeRiesgo[]
}

/** La clave de un factor: la categoría y el nombre literal del formulario. */
export const claveRiesgo = (categoria: string, factor: string) => `${categoria}|${factor}`

export const examenesPorRiesgoService = {
  /** Solo los factores que tienen exámenes asignados. */
  listar: () =>
    api.get<ApiResponse<RiesgoConExamenes[]>>('/dispensario/fichas-sso/examenes-por-riesgo')
      .then(r => r.data.datos ?? []),

  /** Reemplaza los exámenes de un factor; una lista vacía lo deja sin ninguno. */
  guardar: (data: { categoria: string; factor: string; examenes: number[] }) =>
    api.put<ApiResponse<RiesgoConExamenes>>('/dispensario/fichas-sso/examenes-por-riesgo', data)
      .then(r => r.data.datos),
}
