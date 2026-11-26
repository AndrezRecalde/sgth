import api from '@/lib/axios'
import type {
  AnotacionAccion, ApiResponse, MovimientoPersonal, PaginatedResponse,
} from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import type { ActualizarBorradorData, FiltrosBandeja, TransicionarData } from './movimiento.types'

// Se reexportan para que quien los importaba de aquí no tenga que cambiar.
export type { ActualizarBorradorData, FiltrosBandeja, TransicionarData }

/*
| Los actos de una autoridad electa (fase 4.1 y 4.1b). Cada uno manda lo suyo
| y nada de los otros: el backend limpia igualmente, pero un número de
| credencial viajando en una cesación no significa nada y confunde a quien
| revisa el borrador.
*/
export type InicioDelPeriodoData = {
  clase: 'inicio_periodo'
  causal: 'eleccion' | 'designacion_del_consejo'
  descripcion: string
  fecha_efectiva: string
  unidad_destino_id: number
  puesto_destino_id: number
  remuneracion_propuesta: number | null
  fecha_fin_propuesta?: string | null
  credencial_cne_numero?: string | null
  credencial_cne_fecha?: string | null
  acta_posesion_numero?: string | null
  acto_origen_numero?: string | null
  acto_origen_fecha?: string | null
  /** Siempre 'resolucion': es lo que emite el Consejo (COOTAD Art. 47 n). */
  acto_origen_tipo?: 'resolucion'
}

export type LicenciaDelConsejoData = {
  clase: 'licencia_consejo'
  descripcion: string
  /** Rige el día en que empieza: para una ausencia son el mismo día. */
  fecha_efectiva: string
  fecha_inicio: string
  fecha_fin: string
  con_remuneracion: boolean
  prorroga_causa: 'enfermedad_catastrofica' | 'calamidad_domestica' | null
  resolucion_numero: string
}

export type CesacionDeAutoridadData = {
  clase: 'cesacion'
  causal: 'renuncia' | 'fallecimiento' | 'revocatoria_del_mandato' | 'remocion_por_el_consejo'
  descripcion: string
  fecha_efectiva: string
  resolucion_numero: string
}

export type ActoDeAutoridadData =
  | InicioDelPeriodoData
  | LicenciaDelConsejoData
  | CesacionDeAutoridadData

/** Espeja las constantes de `AvisoFinancieroSancionService`. */
export type AvisoFinanciero = 'enviado' | 'sin_destinatario' | 'fallo_envio'

export const movimientoService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<MovimientoPersonal[]>>(
        `/expediente/servidores/${servidorId}/movimientos`,
      )
      .then((r) => r.data.datos ?? []),

  /**
   * El inicio del período de una autoridad electa (fase 4.1).
   *
   * Tiene método propio porque su cuerpo no es el del formulario general: no
   * lleva nombramiento, ni tipo de acto del Ministerio, ni firmantes. Pasarlo
   * como un `MovimientoFormData` a medias obligaba a mentirle al tipo.
   */
  crearActoDeAutoridad: (servidorId: number, data: ActoDeAutoridadData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(
        `/expediente/servidores/${servidorId}/movimientos`, data,
      )
      .then((r) => r.data.datos),

  crear: (servidorId: number, data: MovimientoFormData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(
        `/expediente/servidores/${servidorId}/movimientos`, data,
      )
      .then((r) => r.data.datos),

  /**
   * Bandeja transversal: acciones de personal de todos los servidores.
   *
   * El endpoint pagina, y hasta el 2026-09-27 el tipo declaraba solo `{ data }`
   * y descartaba el resto del sobre. La tabla se pintaba sin paginador: a partir
   * de la acción 21 —el `per_page` por defecto del backend— el resto quedaba
   * invisible y sin forma de alcanzarlo, y el rótulo «N acción(es) en la vista»
   * contaba las de la primera página como si fueran todas.
   */
  listarBandeja: (params?: FiltrosBandeja) =>
    api
      .get<ApiResponse<PaginatedResponse<MovimientoPersonal>>>(
        '/expediente/movimientos', { params },
      )
      .then((r) => r.data.datos),

  /** Detalle completo de una acción, para el cajón de revisión. */
  obtener: (movimientoId: number) =>
    api
      .get<ApiResponse<MovimientoPersonal>>(`/expediente/movimientos/${movimientoId}`)
      .then((r) => r.data.datos),

  /**
   * Con la acción viene, en `meta`, si una multa o una suspensión salió por
   * correo a Gestión Financiera al registrarse o al anularse (2026-10-04).
   */
  transicionar: (movimientoId: number, data: TransicionarData) =>
    api
      .put<ApiResponse<MovimientoPersonal, { aviso_financiero?: AvisoFinanciero }>>(
        `/expediente/movimientos/${movimientoId}/transicionar`, data,
      )
      .then((r) => ({
        movimiento: r.data.datos,
        avisoFinanciero: r.data.meta?.aviso_financiero ?? null,
      })),

  /**
   * Edita una acción de personal que sigue en borrador. El backend rechaza
   * cualquier otro estado: una vez suscrita, el documento ya circuló.
   */
  actualizarBorrador: (movimientoId: number, data: ActualizarBorradorData) =>
    api
      .put<ApiResponse<MovimientoPersonal>>(
        `/expediente/movimientos/${movimientoId}`, data,
      )
      .then((r) => r.data.datos),

  /**
   * Anota en una acción ya emitida, en vez de corregirla: el documento sigue
   * diciendo lo que se firmó (fase 1.4).
   */
  anotar: (movimientoId: number, texto: string) =>
    api
      .post<ApiResponse<AnotacionAccion>>(
        `/expediente/movimientos/${movimientoId}/anotaciones`, { texto },
      )
      .then((r) => r.data.datos),

  descargarPdf: (movimientoId: number) =>
    api
      .get(`/expediente/movimientos/${movimientoId}/accion-personal-pdf`, {
        responseType: 'blob',
      })
      .then((r) => r.data),

  /** El documento con la marca BORRADOR, de lo que todavía no se registra (fase 6.2). */
  vistaPrevia: (movimientoId: number) =>
    api
      .get<Blob>(`/expediente/movimientos/${movimientoId}/vista-previa`, { responseType: 'blob' })
      .then((r) => r.data),
}
