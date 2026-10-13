/**
 * La mesa de ayuda de TIC (fase 3), a partir de `TicketResource` y
 * `ComentarioTicketResource`. Se declaran a mano, como en el inventario.
 */

export type EstadoTicket =
  | 'abierto' | 'asignado' | 'en_proceso' | 'pendiente_usuario' | 'resuelto' | 'cerrado' | 'anulado'

export type PrioridadTicket = 'baja' | 'media' | 'alta' | 'urgente'

export type EstadoSla = 'en_plazo' | 'por_vencer' | 'vencido' | 'pausado' | 'cumplido' | 'incumplido'

export type ComentarioTicket = {
  id: number
  ticket_id: number
  comentario: string
  /** Nota entre quienes atienden: el solicitante no la recibe. */
  es_interno: boolean
  autor_id: number
  autor?: string | null
  tiene_archivo: boolean
  archivo_nombre: string | null
  created_at: string
}

/** `TicketResource`. Las fechas llegan en ISO con zona. */
export type Ticket = {
  id: number
  folio: string
  estado: EstadoTicket
  estado_etiqueta: string
  prioridad: PrioridadTicket
  prioridad_etiqueta: string
  canal: 'portal' | 'tic'
  tipo_ticket: string
  nivel: number
  asunto: string
  descripcion: string
  solucion: string | null
  reabierto_veces: number
  motivo_anulacion: string | null
  created_at: string
  resuelto_en: string | null
  fecha_cierre: string | null
  anulado_en: string | null
  solicitante_id: number
  tecnico_id: number | null
  sla: {
    estado: EstadoSla | null
    respuesta_horas: number | null
    resolucion_horas: number | null
    vence_respuesta_en: string | null
    primera_respuesta_en: string | null
    vence_en: string | null
    pausado_desde: string | null
    vencido_en: string | null
  }
  categoria?: { id: number; nombre: string; es_hardware: boolean } | null
  solicitante?: { id: number; cedula: string; nombre: string; unidad: string | null } | null
  unidad_tic?: { id: number; nombre: string } | null
  tecnico?: { id: number; nombre: string } | null
  bien?: { id: number; codigo_institucional: string; tipo: string | null; marca: string | null; modelo: string | null } | null
  registrado_por?: string | null
  resuelto_por?: string | null
  anulado_por?: string | null
  comentarios?: ComentarioTicket[]
  historial?: { estado_anterior: EstadoTicket | null; estado_nuevo: EstadoTicket; etiqueta: string; usuario: string | null; detalle: string | null; fecha: string }[]
  encuesta?: { calificacion: number; comentarios: string | null } | null
}

/** `GET helpdesk/tickets/catalogos`. */
export type CatalogosHelpdesk = {
  categorias: { id: number; nombre: string; es_hardware: boolean; descripcion: string | null; unidad_tic_id: number | null }[]
  prioridades: { valor: PrioridadTicket; etiqueta: string }[]
  subunidades: { id: number; nombre: string }[]
  tecnicos: { id: number; nombre: string }[]
}

export type FiltrosTickets = {
  /** «Mis tickets»: los que abrió, aunque además atienda. */
  propios?: boolean
  vista?: 'sin_asignar' | 'mios' | 'en_curso' | 'vencidos' | 'resueltos' | 'cerrados'
  estado?: EstadoTicket
  search?: string
}

export type NuevoTicketData = {
  categoria_id: number
  asunto: string
  descripcion: string
  bien_informatico_id: number | null
  archivo: File | null
}
