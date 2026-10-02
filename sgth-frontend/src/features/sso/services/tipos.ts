/**
 * Los tipos del módulo SSO.
 *
 * Los de las entidades **no se escriben aquí**: son un alias del esquema que
 * `api.generated.ts` produce a partir del recurso del backend, como en el
 * resto del sistema (`Puesto`, `ExtensionTelefonica`…). Escritos a mano
 * decían lo que creíamos que devolvía el API, no lo que devuelve: `motivo` y
 * `categoria` eran `string` donde el backend tiene un enum cerrado, el tipo de
 * la entrega de EPP declaraba relaciones que el listado no mandaba, y nada de
 * eso lo podía detectar el compilador.
 *
 * Para regenerar, desde `sgth-frontend`: `npm run types:sync`.
 *
 * Lo que sigue escrito a mano son las respuestas **compuestas**: el reporte de
 * EPP, los dos juegos de índices y la lista de verificación no son entidades,
 * las arma un servicio y no tienen recurso del que inferirlas. El día que lo
 * tengan, bajan también de `api.generated.ts`.
 */

import type { components } from '@/types/api.generated'

type Esquemas = components['schemas']

// ── Entidades: del recurso del backend ───────

export type RiesgoLaboral          = Esquemas['RiesgoLaboralResource']
export type AccidenteTrabajo       = Esquemas['AccidenteTrabajoResource']
export type EquipoProteccion       = Esquemas['EquipoProteccionResource']
export type InspeccionSso          = Esquemas['InspeccionSsoResource']
export type CapacitacionSso        = Esquemas['CapacitacionSsoResource']
export type EppEntrega             = Esquemas['EppEntregaResource']
export type PuestoEpp              = Esquemas['PuestoEppResource']
export type HorasTrabajadasPeriodo = Esquemas['HorasTrabajadasPeriodoResource']
export type FactorRiesgoCatalogo   = Esquemas['FactorRiesgoCatalogo']
export type NormativaLegalSso      = Esquemas['NormativaLegalSso']
export type CumplimientoNormativa  = Esquemas['CumplimientoNormativa']

// ── Enums del dominio ────────────────────────

export type CategoriaFactorRiesgo = Esquemas['CategoriaFactorRiesgo']
export type MotivoEntregaEpp      = Esquemas['MotivoEntregaEpp']

// ── Respuestas compuestas de un servicio ─────

export interface ReporteEppFila {
  servidor_id: number
  servidor_nombre: string
  puesto: string
  total_entregas: number
  total_devoluciones: number
  total_reposiciones: number
  equipos: { equipo: string; fecha: string; motivo: string; cantidad: number }[]
}

export interface ReporteEppEntregas {
  consolidado: ReporteEppFila[]
  totales: { total_registros: number; total_servidores: number }
}

/** De dónde salieron las horas del denominador (ver HorasTrabajadas en el backend). */
export type OrigenHorasTrabajadas = 'periodo_exacto' | 'suma_de_meses'
export type AlcanceHorasTrabajadas = 'institucional' | 'unidad' | 'suma_de_unidades'

export interface IndicadoresReactivos {
  periodo: string
  sin_datos: boolean
  mensaje?: string
  numero_lesiones: number
  dias_perdidos: number
  horas_trabajadas: number
  /**
   * El período anual puede componerse de sus meses y el total institucional
   * puede salir de la suma de unidades, así que dos consultas del mismo período
   * pueden apoyarse en cifras distintas. `detalle` es la frase que lo explica
   * en pantalla; sin ella el índice no se puede auditar.
   */
  horas_trabajadas_origen: OrigenHorasTrabajadas | null
  horas_trabajadas_alcance: AlcanceHorasTrabajadas | null
  horas_trabajadas_detalle: string | null
  indice_frecuencia: number | null
  indice_gravedad: number | null
  tasa_riesgo: number | null
}

export interface IndicadoresProactivos {
  periodo: string
  inspecciones_realizadas: number
  capacitaciones_realizadas: number
  horas_capacitacion_total: number
  cobertura_epp: {
    total_puestos_con_epp_requerido: number
    puestos_con_entrega_en_periodo: number
    porcentaje: number | null
  }
}

export interface FilaListaVerificacion {
  normativa: NormativaLegalSso
  cumplimiento: CumplimientoNormativa | null
  estado: string
}

export interface ListaVerificacionCumplimiento {
  periodo: string
  filas: FilaListaVerificacion[]
  totales: {
    total: number
    cumple: number
    no_cumple: number
    en_proceso: number
    no_registrado: number
  }
}
