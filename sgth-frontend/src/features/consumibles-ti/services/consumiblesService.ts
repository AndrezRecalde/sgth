import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type TipoConsumible =
  | 'toner' | 'tinta' | 'cartucho' | 'cinta' | 'unidad_imagen' | 'kit_mantenimiento' | 'otro' | 'repuesto'

export type ColorConsumible = 'negro' | 'cian' | 'magenta' | 'amarillo' | 'tricolor'

export type PresentacionConsumible = 'unidad' | 'botella' | 'caja'

export type TipoMovimiento = 'apertura' | 'ingreso' | 'entrega' | 'ajuste'

export type DocumentoIngreso = 'egreso_bodega' | 'acta' | 'memorando'

export type MotivoAjuste = 'dano' | 'caducidad' | 'diferencia_conteo' | 'otro'

export type ModeloCompatible = { id: number; marca_id: number; marca: string | null; modelo: string }

/** Una fila del catálogo (`ConsumibleService::presentar`). */
export type Consumible = {
  id: number
  codigo_bodega: string | null
  descripcion: string
  tipo: TipoConsumible
  /** Los de impresión van a un equipo compatible; el repuesto, a cualquiera. */
  de_impresion: boolean
  color: ColorConsumible | null
  presentacion: PresentacionConsumible
  guarda_tic: boolean
  /** Solo si lo guarda TIC. */
  stock_minimo: number | null
  activo: boolean
  /** Solo si lo guarda TIC: lo que despacha Bodega no lleva saldo. */
  saldo: number | null
  bajo_minimo: boolean
  modelos: ModeloCompatible[]
}

export type EquipoDelModelo = { id: number; codigo_institucional: string; ubicacion: string | null; custodio: string | null }

/** `ConsumibleService::detalle`: cada modelo con los equipos del inventario que coinciden. */
export type DetalleConsumible = Omit<Consumible, 'modelos'> & {
  modelos: (ModeloCompatible & { equipos: EquipoDelModelo[] })[]
  creado_por: string | null
  creado_en: string | null
}

/** `MovimientoConsumibleService::presentar`. */
export type Movimiento = {
  id: number
  consumible_id: number
  tipo: TipoMovimiento
  /** Con signo: suma la apertura y el ingreso, resta la entrega; el ajuste, de los dos lados. */
  cantidad: number
  afecta_saldo: boolean
  fecha: string
  equipo: { id: number; codigo_institucional: string; descripcion: string } | null
  retira_id: number | null
  retira: string | null
  unidad: string | null
  ticket_id: number | null
  ticket: string | null
  tipo_documento: DocumentoIngreso | null
  numero_documento: string | null
  fecha_compra: string | null
  motivo_ajuste: MotivoAjuste | null
  observacion: string | null
  registrado_por: string | null
  registrado_en: string | null
  anulado_en: string | null
  anulado_por: string | null
  motivo_anulacion: string | null
}

/** Una fila del kárdex: el saldo que dejó (null si no cuenta: anulado o de Bodega). */
export type FilaKardex = Movimiento & { saldo: number | null }

export type Kardex = { saldo_inicial: number | null; filas: FilaKardex[]; saldo_final: number | null }

/** `ConsumibleService::delEquipo`: la sección de la ficha del equipo. */
export type ConsumiblesDelEquipo = {
  compatibles: Consumible[]
  entregas: { id: number; fecha: string; consumible_id: number; consumible: string | null; cantidad: number; retira: string | null }[]
  ultima_entrega: string | null
  dias_desde_ultima: number | null
  /** El texto libre que se anotaba en la ficha antes de la 5.4 [P11]. */
  consumible_anterior: string | null
}

export type FiltrosConsumibles = {
  search?: string
  tipo?: TipoConsumible
  guarda_tic?: boolean
  incluir_inactivos?: boolean
  bajo_minimo?: boolean
  bien_informatico_id?: number
  /** Con `modelo`: lo que sirve a ese modelo; sin él, a cualquier modelo de la marca. */
  marca_id?: number
  modelo?: string
}

export type GuardarConsumibleData = {
  codigo_bodega: string | null
  descripcion: string
  tipo: TipoConsumible
  color: ColorConsumible | null
  presentacion: PresentacionConsumible
  guarda_tic: boolean
  stock_minimo: number
  activo?: boolean
  modelos: { marca_id: number; modelo: string }[]
}

export type Renglon = { consumible_id: number; cantidad: number }

export type IngresoData = {
  fecha: string
  tipo_documento: DocumentoIngreso
  numero_documento: string
  fecha_compra: string | null
  observacion: string | null
  items: Renglon[]
}

export type EntregaData = {
  fecha: string
  bien_informatico_id: number | null
  servidor_retira_id: number | null
  ticket_id?: number | null
  observacion: string | null
  items: Renglon[]
}

export type AperturaData = { fecha: string; cantidad: number; observacion: string | null }

export type AjusteData = { fecha: string; cantidad: number; motivo_ajuste: MotivoAjuste; observacion: string }

const URL = '/inventario/consumibles'

const banderas = (f: FiltrosConsumibles) => ({
  ...f,
  guarda_tic: f.guarda_tic === undefined ? undefined : Number(f.guarda_tic),
  incluir_inactivos: f.incluir_inactivos ? 1 : undefined,
  bajo_minimo: f.bajo_minimo ? 1 : undefined,
})

/** Los consumibles de impresión y repuestos (`ConsumibleController`, `MovimientoConsumibleController`, fase 5.4). */
export const consumiblesService = {
  listar: (filtros: FiltrosConsumibles) =>
    api.get<ApiResponse<Consumible[]>>(URL, { params: banderas(filtros) }).then((r) => r.data.datos ?? []),
  obtener: (id: number) => api.get<ApiResponse<DetalleConsumible>>(`${URL}/${id}`).then((r) => r.data.datos),
  crear: (data: GuardarConsumibleData) => api.post<ApiResponse<DetalleConsumible>>(URL, data).then((r) => r.data.datos),
  actualizar: (id: number, data: GuardarConsumibleData) =>
    api.put<ApiResponse<DetalleConsumible>>(`${URL}/${id}`, data).then((r) => r.data.datos),
  agregarModelo: (id: number, marca_id: number, modelo: string) =>
    api.post<ApiResponse<DetalleConsumible>>(`${URL}/${id}/modelos`, { marca_id, modelo }).then((r) => r.data.datos),
  quitarModelo: (id: number, modeloId: number) =>
    api.delete<ApiResponse<DetalleConsumible>>(`${URL}/${id}/modelos/${modeloId}`).then((r) => r.data.datos),

  kardex: (id: number, desde: string | null, hasta: string | null) =>
    api.get<ApiResponse<Kardex>>(`${URL}/${id}/kardex`, { params: { desde: desde ?? undefined, hasta: hasta ?? undefined } })
      .then((r) => r.data.datos),
  apertura: (id: number, data: AperturaData) => api.post<ApiResponse<Movimiento>>(`${URL}/${id}/apertura`, data).then((r) => r.data.datos),
  ajuste: (id: number, data: AjusteData) => api.post<ApiResponse<Movimiento>>(`${URL}/${id}/ajustes`, data).then((r) => r.data.datos),
  ingreso: (data: IngresoData) => api.post<ApiResponse<Movimiento[]>>(`${URL}/ingresos`, data).then((r) => r.data.datos ?? []),
  entrega: (data: EntregaData) => api.post<ApiResponse<Movimiento[]>>(`${URL}/entregas`, data).then((r) => r.data.datos ?? []),
  anular: (movimientoId: number, motivo: string) =>
    api.post<ApiResponse<Movimiento>>(`${URL}/movimientos/${movimientoId}/anular`, { motivo }).then((r) => r.data.datos),

  delEquipo: (bienId: number) =>
    api.get<ApiResponse<ConsumiblesDelEquipo>>(`/inventario/bienes/${bienId}/consumibles`).then((r) => r.data.datos),
}
