import api from '@/lib/axios'
import type { ApiResponse, MovimientoPersonal } from '@/types/api'

/** Un acto que cierra la prueba: el definitivo o el reintegro del ascendido. */
type SalidaDeLaPrueba = { id: number; estado: string; codigo_registro: string | null }

/**
 * Quien está en período de prueba (LOSEP 17 b.5; fases 3.2 y 3.3): quien
 * ingresó por concurso, tres meses, o quien ascendió, seis.
 */
export type PeriodoDePrueba = {
  contrato_id: number
  servidor: { id: number | null; nombre: string; cedula: string | null }
  /** Por qué está en prueba: ingresó por concurso o ascendió. */
  motivo: 'de_prueba' | 'ascenso'
  motivo_etiqueta: string
  puesto: string | null
  unidad: string | null
  /** El del ascendido, que se le guarda mientras dura la prueba. */
  puesto_reservado: string | null
  desde: string | null
  /** El último día de la prueba. */
  hasta: string | null
  dias_restantes: number | null
  /** Ya terminó la prueba: toca el nombramiento definitivo. */
  concluida: boolean
  definitivo: SalidaDeLaPrueba | null
  /** El del ascendido que no la superó. */
  reintegro: SalidaDeLaPrueba | null
}

export type ReintegrarAscensoData = {
  fecha_regreso: string
  informe_uath_numero: string
  descripcion: string
}

export const periodoDePruebaService = {
  listar: () =>
    api.get<ApiResponse<PeriodoDePrueba[]>>('/expediente/periodos-de-prueba').then((r) => r.data.datos),

  /** Prepara en borrador el nombramiento definitivo, cumplida la prueba. */
  prepararDefinitivo: (contratoId: number) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(`/expediente/contratos/${contratoId}/nombramiento-definitivo`)
      .then((r) => r.data.datos),

  /** Prepara en borrador el reintegro del ascendido a su puesto anterior (fase 3.3). */
  reintegrarAscenso: (contratoId: number, data: ReintegrarAscensoData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(`/expediente/contratos/${contratoId}/reintegro-del-ascenso`, data)
      .then((r) => r.data.datos),
}
