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
  /** `es_consumibles`: la categoría que genera la solicitud a bodega (5.4c). */
  categoria?: { id: number; nombre: string; es_hardware: boolean; es_consumibles?: boolean } | null
  solicitante?: { id: number; cedula: string; nombre: string; unidad: string | null } | null
  unidad_tic?: { id: number; nombre: string } | null
  tecnico?: { id: number; nombre: string } | null
  bien?: { id: number; codigo_institucional: string; tipo: string | null; marca: string | null; modelo: string | null } | null
  /** Resuelto sin un equipo del inventario: por qué. */
  motivo_sin_equipo?: string | null
  registrado_por?: string | null
  resuelto_por?: string | null
  anulado_por?: string | null
  comentarios?: ComentarioTicket[]
  historial?: { estado_anterior: EstadoTicket | null; estado_nuevo: EstadoTicket; etiqueta: string; usuario: string | null; detalle: string | null; fecha: string }[]
  encuesta?: { calificacion: number; comentarios: string | null } | null
}

/** Un problema frecuente tal como se ofrece al pedir ayuda (`TicketService::catalogos`). */
export type ProblemaOfrecido = {
  id: number
  nombre: string
  descripcion: string
  categoria_id: number
  categoria: string | null
  unidad_tic: string | null
  prioridad: PrioridadTicket
  tipo_ticket: TipoTicket
}

/** `GET helpdesk/tickets/catalogos`. */
export type CatalogosHelpdesk = {
  categorias: { id: number; nombre: string; es_hardware: boolean; descripcion: string | null; unidad_tic_id: number | null }[]
  /** Los botones de «Pedir ayuda a TIC», en el orden de TIC. */
  problemas: ProblemaOfrecido[]
  prioridades: { valor: PrioridadTicket; etiqueta: string }[]
  subunidades: { id: number; nombre: string }[]
  /** Los usuarios que atienden tickets, con su ficha de técnico si la tienen (D6). */
  tecnicos: { id: number; nombre: string; unidad_tic_id: number | null; nivel: number | null }[]
}

/** `GET helpdesk/categorias` (fase 3.5): también las desactivadas. */
export type CategoriaTicketConfig = {
  id: number
  nombre: string
  descripcion: string | null
  es_hardware: boolean
  activa: boolean
  unidad_tic_id: number | null
  unidad_tic: string | null
  tickets_count: number
}

export type GuardarCategoriaData = Partial<{
  nombre: string
  descripcion: string | null
  es_hardware: boolean
  unidad_tic_id: number | null
  activa: boolean
}>

export type TipoTicket = 'incidente' | 'solicitud'

/**
 * Un problema frecuente de «Pedir ayuda a TIC» (`ProblemaFrecuenteService::fila`):
 * el nombre es también el asunto del ticket, y la descripción la escribe TIC.
 */
export type ProblemaFrecuenteConfig = {
  id: number
  nombre: string
  descripcion: string
  categoria_id: number
  categoria: string | null
  /** Con la categoría inactiva deja de ofrecerse, aunque esté activo. */
  categoria_activa: boolean
  unidad_tic: string | null
  tipo_ticket: TipoTicket
  prioridad: PrioridadTicket
  orden: number
  activo: boolean
  /** Los tickets abiertos con él en los últimos tres meses. */
  tickets_recientes: number
}

export type GuardarProblemaData = Partial<{
  nombre: string
  descripcion: string
  categoria_id: number
  tipo_ticket: TipoTicket
  prioridad: PrioridadTicket
  activo: boolean
}>

/** Una fila de `slas`: sin categoría es la regla general de la prioridad (D7). */
export type SlaRegla = {
  id: number
  prioridad: PrioridadTicket
  categoria_id: number | null
  categoria?: { id: number; nombre: string } | null
  tiempo_respuesta_horas: number
  tiempo_resolucion_horas: number
}

/** `GET helpdesk/slas`: las filas y lo que rige cuando una prioridad no tiene fila general. */
export type TablaSla = {
  reglas: SlaRegla[]
  por_defecto: Record<PrioridadTicket, { respuesta: number; resolucion: number }>
}

export type GuardarSlaData = {
  prioridad: PrioridadTicket
  categoria_id: number | null
  tiempo_respuesta_horas: number
  tiempo_resolucion_horas: number
}

/** `GET helpdesk/tecnicos`: la subunidad de TIC y el nivel de quien atiende. */
export type TecnicoHelpdesk = {
  id: number
  user_id: number
  nombre: string | null
  usuario_ti: string | null
  unidad_tic_id: number | null
  unidad_tic: string | null
  nivel: number
  estado: boolean
}

export type GuardarTecnicoData = Partial<{ user_id: number; unidad_tic_id: number; nivel: number; estado: boolean }>

export type VistaBandejaTickets = 'sin_asignar' | 'mios' | 'en_curso' | 'vencidos' | 'resueltos' | 'cerrados'

export type FiltrosTickets = {
  /** «Mis tickets»: los que abrió, aunque además atienda. */
  propios?: boolean
  vista?: VistaBandejaTickets
  estado?: EstadoTicket
  search?: string
  categoria_id?: number
  prioridad?: PrioridadTicket
  unidad_tic_id?: number
  tecnico_id?: number
}

/** `GET helpdesk/tickets/resumen`: los contadores de la bandeja de TIC. */
export type ResumenTickets = { sin_asignar: number; mios: number; en_curso: number; vencidos: number; resueltos: number }

export type AsignarTicketData = { tecnico_id: number | null; unidad_tic_id: number | null; prioridad: PrioridadTicket | null }

export type ResolverTicketData = {
  solucion: string
  registrar_mantenimiento: boolean
  estado_equipo: string | null
  /** Uno de hardware sin equipo: el que se atendió, o por qué no es del inventario (2026-11-05). */
  bien_informatico_id?: number | null
  motivo_sin_equipo?: string | null
}

/**
 * Abrir un ticket. Con un problema frecuente, la categoría, el asunto, la
 * descripción y el tipo salen de él, y solo va un comentario opcional; sin
 * él, se mandan como siempre.
 */
export type NuevoTicketData = {
  problema_frecuente_id?: number | null
  comentario?: string | null
  categoria_id?: number
  asunto?: string
  descripcion?: string
  bien_informatico_id: number | null
  archivo?: File | null
  /** Solo TIC: a nombre de quién y con qué prioridad. */
  servidor_id?: number
  prioridad?: PrioridadTicket
}
