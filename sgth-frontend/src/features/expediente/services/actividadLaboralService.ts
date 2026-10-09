import api from '@/lib/axios'
import type { ApiResponse, ClaseAccionPersonal, ContratoConRelaciones } from '@/types/api'

/** Una acción de personal ocurrida sobre un vínculo. */
export type AccionSobreVinculo = {
  id: number
  tipo_movimiento: string | null
  subtipo_movimiento: string | null
  clase: ClaseAccionPersonal | null
  /** La causal cuando la hay; si no, el nombre de la clase. */
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

/**
 * Lo que le pasó al contrato fuera de las acciones de personal: se creó, se
 * cerró, se le movió el plazo. Solo la reprogramación trae las tres últimas —
 * el motivo es obligatorio justamente para que se pueda leer después.
 */
export type CambioDeVinculo = {
  id: number
  descripcion: string | null
  fecha: string | null
  por: string | null
  fecha_fin_anterior: string | null
  fecha_fin_nueva: string | null
  motivo: string | null
}

/** Lo que la bitácora del vínculo sabe anotar (TipoEventoVinculo). */
export type TipoNovedadVinculo =
  | 'contrato_registrado'
  | 'subrogacion_finalizada'
  | 'subrogacion_cancelada'
  // Tipos anteriores que el sistema ya no genera; solo los trae el histórico.
  | 'cambio_puesto'
  | 'cambio_regimen'
  | 'egreso'

/**
 * Una entrada de la bitácora del vínculo: algo que le pasó al contrato sin ser
 * un acto —se registró sin acción de personal, una subrogación terminó antes—.
 * Hasta la fase 1.2 venían mezcladas con las acciones.
 */
export type NovedadDelVinculo = {
  id: number
  tipo: TipoNovedadVinculo
  etiqueta: string
  fecha: string | null
  descripcion: string
  /** La acción a la que se refiere, si la hay: la de la subrogación. */
  movimiento_personal_id: number | null
  registrado_por: string | null
}

export type VinculoConActividad = {
  contrato: ContratoConRelaciones
  acciones: AccionSobreVinculo[]
  novedades: NovedadDelVinculo[]
  situacion: SituacionVinculo | null
  reemplaza_a: ReemplazoDeVinculo | null
  cambios: CambioDeVinculo[]
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
