import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { components } from '@/types/api.generated'

/**
 * La regularización del tipo de puesto (docs/diseno-manual-de-puestos.md, P9,
 * PR 4). Todos los puestos nacieron de plantilla: el backend propone y Talento
 * Humano confirma cada caso.
 */
export type PuestoPorRegularizar = components['schemas']['PuestoPorRegularizarResource']
export type ContratoPorRegularizar = components['schemas']['ContratoPorRegularizarResource']

export type PorRevisar = {
  puestos: PuestoPorRegularizar[]
  contratos: ContratoPorRegularizar[]
}

const URL = '/estructura/tipo-de-puesto'

export const regularizacionTipoService = {
  porRevisar: () =>
    api.get<ApiResponse<PorRevisar>>(`${URL}/por-revisar`).then((r) => r.data.datos),

  pasarAContrato: (puestoId: number) =>
    api.post<ApiResponse<unknown>>(`${URL}/puestos/${puestoId}/pasar-a-contrato`).then((r) => r.data.datos),

  reubicar: (contratoId: number, puestoDestinoId: number) =>
    api.post<ApiResponse<unknown>>(`${URL}/contratos/${contratoId}/reubicar`, {
      puesto_destino_id: puestoDestinoId,
    }).then((r) => r.data.datos),
}
