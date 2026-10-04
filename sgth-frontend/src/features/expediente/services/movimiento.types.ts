import type { EstadoAccionPersonal, TipoMovimientoPersonal } from '@/types/api'

// Los tipos de las peticiones de acciones de personal, fuera del servicio
// para que este no pase de 100 líneas (regla 02).

/**
 * Filtros de la bandeja. `servidor_id` lo acepta el backend y todavía no lo usa
 * ninguna pantalla; se declara aquí porque es donde se ve qué admite el
 * endpoint.
 */
export type FiltrosBandeja = {
  estado?: EstadoAccionPersonal
  tipo_movimiento?: TipoMovimientoPersonal
  servidor_id?: number
  anio?: number
  page?: number
  per_page?: number
}

/**
 * Payload de una transición. Los datos del vínculo solo aplican al pasar un
 * ingreso a 'registrada': se completan en el acto de aprobar, porque una
 * acción suscrita ya no se edita.
 */
export type TransicionarData = {
  estado: string
  dictamen_presupuestario_ref?: string | null
  numero_contrato?: string | null
  remuneracion_propuesta?: number | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  resolucion_numero?: string | null
  fecha_fin_propuesta?: string | null
  /**
   * Por qué se anula. Obligatorio al pasar a 'anulada' —el backend lo exige con
   * `required_if`—: anular es un acto sobre otro acto y tiene que quedar dicho.
   */
  motivo_anulacion?: string | null
}

/**
 * Campos editables mientras la acción de personal está en borrador. Excluye
 * 'tipo_movimiento' y 'subtipo_movimiento' a propósito: cambiar la naturaleza
 * del acto no es editarlo — el backend tampoco los acepta.
 */
export type ActualizarBorradorData = {
  descripcion?: string
  fecha_efectiva?: string
  fecha_inicio?: string | null
  fecha_fin?: string | null
  unidad_destino_id?: number | null
  puesto_destino_id?: number | null
  tipo_nombramiento_propuesto?: string | null
  remuneracion_propuesta?: number | null
  fecha_fin_propuesta?: string | null
  numero_contrato?: string | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  requiere_dictamen_medico?: boolean | null
  resolucion_numero?: string | null
  observacion?: string | null
  lugar_trabajo?: string | null
  caucionado?: boolean | null
  caucion_numero?: string | null
  caucion_fecha?: string | null
}
