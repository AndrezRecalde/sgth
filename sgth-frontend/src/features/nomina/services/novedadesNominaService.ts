import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type ConceptoNovedadNomina =
  | 'ingreso'
  | 'salida'
  | 'cambio_remuneracion'
  | 'dias_sin_remuneracion'
  | 'subrogacion'
  | 'encargo'
  | 'multa'
  | 'remuneraciones_pendientes'
  | 'vacaciones_no_gozadas'
  | 'indemnizacion'

/**
 * Una novedad para nómina (fase 7.3a): lo que Financiero aplica en su sistema,
 * con un monto de referencia. Positivo, lo que se paga; negativo, lo que se
 * descuenta.
 */
export type NovedadNomina = {
  clave: string
  concepto: ConceptoNovedadNomina
  concepto_etiqueta: string
  /** El mes al que corresponde: el mismo, o uno anterior si es un ajuste. */
  mes_afectado: string
  es_ajuste: boolean
  /** En un ajuste, si la novedad sigue en pie: false cuando se revierte. */
  vigente: boolean
  servidor_id: number
  servidor: string
  cedula: string | null
  contrato_id: number | null
  movimiento_id: number | null
  subrogacion_id: number | null
  /** El número del acto que la respalda. */
  acto: string | null
  desde: string | null
  hasta: string | null
  dias: number | null
  base: number | null
  monto: number | null
  detalle: string
}

export type EntregaNovedades = {
  entregada_en: string | null
  entregada_por: string | null
  novedades: number
  ajustes: number
  destinatario: string | null
  /** Cómo salió el correo a Financiero: enviado, sin destinatario o falló. */
  aviso: 'enviado' | 'sin_destinatario' | 'fallo_envio' | null
}

export type NovedadesDelMes = {
  periodo: string
  etiqueta: string
  entrega: EntregaNovedades | null
  puede_entregarse: boolean
  novedades: NovedadNomina[]
  /** Lo que cambió en meses ya entregados, que va en este. */
  ajustes: NovedadNomina[]
  /** En un mes entregado, lo que cambió después: va como ajuste en el siguiente. */
  cambios_tras_la_entrega: NovedadNomina[]
  resumen: { concepto: ConceptoNovedadNomina; etiqueta: string; cantidad: number; monto: number }[]
}

export const novedadesNominaService = {
  delMes: (periodo: string) =>
    api
      .get<ApiResponse<NovedadesDelMes>>('/nomina/novedades', { params: { periodo } })
      .then((r) => r.data.datos),

  excel: (periodo: string) =>
    api
      .get('/nomina/novedades/excel', { params: { periodo }, responseType: 'blob' })
      .then((r) => r.data as Blob),

  /** Congela el mes y se lo envía al jefe de Gestión Financiera. */
  entregar: (periodo: string) =>
    api
      .post<ApiResponse<NovedadesDelMes, { aviso_financiero?: EntregaNovedades['aviso'] }>>(
        '/nomina/novedades/entregar',
        { periodo },
      )
      .then((r) => r.data),
}
