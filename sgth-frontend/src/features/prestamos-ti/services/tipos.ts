/**
 * El préstamo de equipos de TIC (fase 2), a partir de `PrestamoEquipoResource`,
 * `ReglasPrestamoResource` y `DisponibilidadPrestamoService`. Se declaran a mano,
 * como en el inventario: Scramble no infiere bien las llaves ni los booleanos.
 */

export type EstadoPrestamo =
  | 'solicitado' | 'reservado' | 'en_curso' | 'devuelto' | 'rechazado' | 'cancelado' | 'no_retirado'

export type CondicionFisica = 'bueno' | 'regular' | 'malo'

export type ItemPrestamo = {
  id: number
  bien: { id: number; codigo_institucional: string; tipo: string | null; marca: string | null; modelo: string | null } | null
  accesorios: string | null
  condicion_entrega: CondicionFisica | null
  condicion_devolucion: CondicionFisica | null
  novedad: string | null
}

/** `PrestamoEquipoResource`. Las fechas llegan en ISO con zona. */
export type PrestamoEquipo = {
  id: number
  folio: string
  estado: EstadoPrestamo
  estado_etiqueta: string
  /** En curso con la hora de devolución pasada. */
  vencido: boolean
  /** Solicitado o reservado: todavía no se entrega. */
  cancelable: boolean
  directo: boolean
  servidor_id: number
  servidor?: { id: number; cedula: string; nombre: string } | null
  unidad?: { id: number; nombre: string; acronimo: string | null } | null
  motivo: string
  lugar_uso: string
  fuera_del_edificio: boolean
  inicio_previsto: string
  fin_previsto: string
  aprobado_en: string | null
  rechazado_en: string | null
  motivo_rechazo: string | null
  cancelado_en: string | null
  motivo_cancelacion: string | null
  entregado_en: string | null
  devuelto_en: string | null
  observaciones: string | null
  registrado_por?: string | null
  aprobado_por?: string | null
  rechazado_por?: string | null
  cancelado_por?: string | null
  entregado_por?: string | null
  recibido_por?: string | null
  items?: ItemPrestamo[]
  historial?: {
    estado_anterior: EstadoPrestamo | null; estado_nuevo: EstadoPrestamo; etiqueta: string
    usuario: string | null; detalle: string | null; fecha: string
  }[]
}

/** `ReglasPrestamoResource` (D16). */
export type ReglasPrestamo = {
  plazo_maximo_dias_habiles: number
  anticipacion_minima_minutos: number
  hora_apertura: string
  hora_cierre: string
  presta_fines_de_semana: boolean
  bloquea_con_vencido: boolean
  margen_retiro_minutos: number
  actualizado_por?: string | null
  updated_at: string | null
}

/** Un horario ocupado. El folio y el servidor solo llegan a TIC (D15). */
export type BloqueOcupado = {
  inicio: string
  fin: string
  estado: EstadoPrestamo
  prestamo_id?: number
  folio?: string
  servidor?: string | null
}

export type EstadoAhora =
  | { estado: 'disponible'; proxima_reserva: string | null }
  | { estado: 'prestado'; hasta: string; vencido: boolean; folio?: string; servidor?: string | null }
  | { estado: 'reservado'; desde: string; hasta: string; folio?: string; servidor?: string | null }
  | { estado: 'no_disponible'; detalle: string }

/** Un equipo prestable con su estado ahora y sus bloques ocupados en el rango. */
export type EquipoPrestable = {
  id: number
  codigo_institucional: string
  tipo: string | null
  tipo_bien_id: number
  marca: string | null
  modelo: string | null
  ahora: EstadoAhora
  bloques: BloqueOcupado[]
}

export type SolicitarPrestamoData = {
  bienes: number[]
  inicio_previsto: string
  fin_previsto: string
  motivo: string
  lugar_uso: string
  fuera_del_edificio: boolean
}

/** Las pestañas de la bandeja de TIC. */
export type VistaBandeja = 'por_aprobar' | 'reservados' | 'en_curso' | 'vencidos' | 'historial'

export type FiltrosBandeja = {
  vista?: VistaBandeja
  search?: string
}

export type ResumenBandeja = { por_aprobar: number; reservados: number; por_entregar_hoy: number; en_curso: number; vencidos: number }

export type EntregaData = {
  entregado_en: string
  items: { id: number; accesorios: string | null; condicion_entrega: CondicionFisica }[]
  observaciones: string | null
}

export type RecepcionData = {
  devuelto_en: string
  items: { id: number; condicion_devolucion: CondicionFisica; novedad: string | null }[]
  observaciones: string | null
}

export type DirectoData = {
  servidor_id: number
  items: { bien_informatico_id: number; accesorios: string | null; condicion_entrega: CondicionFisica }[]
  fin_previsto: string
  motivo: string
  lugar_uso: string
  fuera_del_edificio: boolean
  observaciones: string | null
}
