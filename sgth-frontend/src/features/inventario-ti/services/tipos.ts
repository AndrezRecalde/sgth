/**
 * Los tipos del inventario de TIC, escritos a partir de los recursos del
 * backend (`app/Http/Resources/InventarioTi`). Scramble no infiere bien las
 * llaves ni los booleanos (ver `types/api.ts`), así que se declaran aquí.
 * Los de la baja van en `tiposBaja.ts`.
 */

import type { BajaBien, BajaVigente } from './tiposBaja'

export type EstadoBien =
  | 'en_uso' | 'disponible' | 'en_mantenimiento' | 'en_garantia' | 'dado_de_baja' | 'extraviado'

export type CondicionFisica = 'bueno' | 'regular' | 'malo'

type Ref = { id: number; nombre: string }

/** `BienInformaticoResource`. */
export type BienInformatico = {
  id: number
  codigo_institucional: string
  codigo_anterior: string | null
  codigo_qr: string | null
  numero_serie: string | null
  modelo: string | null
  tipo_bien_id: number
  subtipo_bien_id: number | null
  marca_id: number
  origen_bien_id: number | null
  ubicacion_id: number | null
  unidad_administrativa_id: number | null
  contrato_vigencia_id: number | null
  bien_padre_id: number | null
  estado_operativo: EstadoBien
  estado_etiqueta: string
  condicion_fisica: CondicionFisica
  fecha_compra: string | null
  fecha_fin_vida_util: string | null
  vida_util_cumplida: boolean
  nombre_red: string | null
  usuario_dominio: string | null
  direccion_ip: string | null
  prestable: boolean
  caracteristicas_tecnicas: Record<string, string> | null
  observaciones: string | null
  componentes_count: number
  tipo?: Ref
  subtipo?: Ref | null
  marca?: Ref
  ubicacion?: { id: number; sede: string; piso: string | null; nombre: string } | null
  contrato_vigencia?: { id: number; proceso: string; fecha_fin: string | null } | null
  origen?: { id: number; tipo_origen: string; entidad_origen: string; identificador_documento: string | null } | null
  custodio: { asignacion_id: number; desde: string | null; servidor_id: number; cedula: string; nombre: string } | null
  unidad: { id: number; nombre: string; acronimo: string | null } | null
  padre?: { id: number; codigo_institucional: string; modelo: string | null; tipo: string | null } | null
  componentes?: { id: number; codigo_institucional: string; modelo: string | null; tipo: string | null; estado_operativo: EstadoBien }[]
  /** La baja propuesta o aprobada (fase 1.6); solo en la ficha. */
  baja?: BajaVigente | null
}

/** Lo que se envía al registrar o corregir un equipo. */
export type GuardarBienData = {
  codigo_institucional: string
  codigo_anterior: string | null
  numero_serie: string | null
  tipo_bien_id: number
  subtipo_bien_id: number | null
  marca_id: number
  modelo: string | null
  fecha_compra: string | null
  ubicacion_id: number | null
  unidad_administrativa_id: number | null
  nombre_red: string | null
  usuario_dominio: string | null
  direccion_ip: string | null
  bien_padre_id: number | null
  contrato_vigencia_id: number | null
  prestable: boolean
  condicion_fisica: CondicionFisica
  estado_operativo?: EstadoBien
  caracteristicas_tecnicas: Record<string, string> | null
  observaciones: string | null
}

export type FiltrosBienes = {
  search?: string
  tipo_bien_id?: number
  marca_id?: number
  ubicacion_id?: number
  unidad_administrativa_id?: number
  contrato_vigencia_id?: number
  estado_operativo?: EstadoBien
  condicion_fisica?: CondicionFisica
  prestable?: boolean
  sin_custodio?: boolean
  solo_principales?: boolean
}

export type Opcion<T extends string = string> = { valor: T; etiqueta: string }

/** `GET /inventario/catalogos`: lo que necesita el formulario de un equipo. */
export type CatalogosInventario = {
  tipos: { id: number; nombre: string; anios_vida_util: number; subtipos: { id: number; tipo_bien_id: number; nombre: string }[] }[]
  marcas: Ref[]
  ubicaciones: { id: number; sede: string; piso: string | null }[]
  contratos: { id: number; proceso: string; fecha_inicio: string | null; fecha_fin: string | null }[]
  estados: Opcion<EstadoBien>[]
  condiciones: Opcion<CondicionFisica>[]
  /** Hasta el arranque de la mesa de ayuda se anota el soporte del sistema antiguo (D1). */
  acepta_soporte_anterior: boolean
}

// ── Configuración ──────────────────────────────────────────────

export type TipoBien = {
  id: number
  nombre: string
  anios_vida_util: number
  bienes_count: number | null
  subtipos?: { id: number; tipo_bien_id: number; nombre: string }[]
}

export type Marca = { id: number; nombre: string; bienes_count: number | null }

export type Ubicacion = {
  id: number
  sede: string
  piso: string | null
  nombre: string
  descripcion: string | null
  bienes_count: number | null
}

export type VentanaCronograma = { id?: number; fecha_desde: string; fecha_hasta: string; descripcion: string | null }

export type ContratoVigencia = {
  id: number
  proceso: string
  proveedor: string | null
  objeto: string | null
  administrador_id: number | null
  fecha_inicio: string | null
  fecha_fin: string | null
  vigente: boolean
  observaciones: string | null
  bienes_count: number | null
  administrador?: { id: number; cedula: string; nombre: string } | null
  cronograma?: VentanaCronograma[]
}

type Persona = { id: number; cedula: string; nombre: string }

/** `AsignacionBienResource`: una custodia. */
export type Asignacion = {
  id: number
  bien_informatico_id: number
  servidor_id: number
  usuario_servidor_id: number | null
  fecha_asignacion: string | null
  fecha_devolucion: string | null
  vigente: boolean
  motivo: 'entrega' | 'traspaso'
  observaciones: string | null
  memorando_numero: string | null
  tiene_memorando_pdf: boolean
  memorando_nombre: string | null
  soporte_anterior: string | null
  custodio?: Persona | null
  unidad?: { id: number; nombre: string; acronimo: string | null } | null
  usuario?: Persona | null
  custodio_anterior?: Persona | null
  registrada_por?: string | null
  bien?: {
    id: number
    codigo_institucional: string
    tipo: string | null
    subtipo: string | null
    marca: string | null
    modelo: string | null
    ubicacion: string | null
  } | null
}

/** Entregar o traspasar: los mismos datos (el equipo, solo al entregar). */
export type EntregaData = {
  bien_informatico_id?: number
  servidor_id: number
  usuario_servidor_id: number | null
  fecha_asignacion: string
  memorando_numero: string | null
  soporte_anterior: string | null
  observaciones: string | null
}

export type DevolucionData = {
  fecha_devolucion: string
  observaciones: string | null
  soporte_anterior: string | null
}

/** `GET /inventario/bienes/{id}/historial`. */
export type HistorialBien = {
  asignaciones: Asignacion[]
  mantenimientos: Mantenimiento[]
  bajas: BajaBien[]
}

export type TipoMantenimiento = 'preventivo' | 'correctivo' | 'repotenciacion'

/** `MantenimientoBienResource`. Los del Excel de TIC no tienen fecha. */
export type Mantenimiento = {
  id: number
  bien_informatico_id: number
  ticket_id: number | null
  tipo_mantenimiento: TipoMantenimiento
  tipo_etiqueta: string
  realizado_por: 'tic' | 'proveedor'
  fecha_mantenimiento: string | null
  descripcion: string
  costo: number
  /** El número de soporte del sistema antiguo (D1). */
  soporte_anterior: string | null
  anulado: boolean
  anulado_en: string | null
  motivo_anulacion: string | null
  tecnico?: { id: number; nombre: string } | null
  contrato_vigencia?: { id: number; proceso: string; proveedor: string | null } | null
  registrado_por?: string | null
  anulado_por?: string | null
  bien?: { id: number; codigo_institucional: string; tipo: string | null; marca: string | null; modelo: string | null } | null
}

export type RegistrarMantenimientoData = {
  bien_informatico_id: number
  tipo_mantenimiento: TipoMantenimiento
  realizado_por: 'tic' | 'proveedor'
  contrato_vigencia_id: number | null
  fecha_mantenimiento: string
  tecnico_id: number | null
  descripcion: string
  costo: number
  soporte_anterior: string | null
  /** En qué estado queda el equipo; «disponible» es «vuelve al servicio». */
  estado_equipo: EstadoBien | null
}

export type FiltrosMantenimientos = {
  search?: string
  tipo_mantenimiento?: TipoMantenimiento
  realizado_por?: 'tic' | 'proveedor'
  incluir_anulados?: boolean
}
