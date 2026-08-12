import api from '@/lib/axios'
import type { ApiResponse, ContratoConRelaciones } from '@/types/api'

/** Una acción de personal ocurrida sobre un vínculo. */
export type AccionSobreVinculo = {
  id: number
  tipo_movimiento: string | null
  subtipo_movimiento: string | null
  etiqueta: string | null
  codigo_registro: string | null
  fecha_efectiva: string | null
  fecha_inicio: string | null
  fecha_fin: string | null
  descripcion: string | null
  unidad_origen: string | null
  unidad_destino: string | null
  puesto_origen: string | null
  puesto_destino: string | null
}

/**
 * Situación derivada de las acciones vigentes hoy — no se almacena en el
 * contrato, se calcula. Null cuando el servidor está en funciones normales.
 */
export type SituacionVinculo = {
  etiqueta: string
  desde: string | null
  hasta: string | null
}

/** Presente solo en los contratos que existen para cubrir a otra persona. */
export type ReemplazoDeVinculo = {
  movimiento_id: number
  servidor: string
  etiqueta: string | null
  hasta: string | null
}

export type VinculoConActividad = {
  contrato: ContratoConRelaciones
  acciones: AccionSobreVinculo[]
  situacion: SituacionVinculo | null
  reemplaza_a: ReemplazoDeVinculo | null
}

/**
 * El plazo es lo único editable de un vínculo ya creado. El motivo es
 * obligatorio porque los dos casos que lo mueven —una prórroga y la corrección
 * de una fecha mal digitada— se ven idénticos en la base, y solo el motivo los
 * distingue después.
 */
export type ReprogramarPlazoData = {
  fecha_fin: string | null
  motivo: string
}

export const actividadLaboralService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<VinculoConActividad[]>>(
        `/expediente/servidores/${servidorId}/actividad-laboral`,
      )
      .then((r) => r.data.datos),

  reprogramarPlazo: (
    servidorId: number,
    contratoId: number,
    data: ReprogramarPlazoData,
  ) =>
    api
      .put<ApiResponse<ContratoConRelaciones>>(
        `/expediente/servidores/${servidorId}/contratos/${contratoId}/plazo`,
        data,
      )
      .then((r) => r.data.datos),
}
