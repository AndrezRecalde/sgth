import api from '@/lib/axios'
import type { ApiResponse, EstadoAccionPersonal } from '@/types/api'

/** El acta de finiquito de un obrero, registrada desde el sistema del Ministerio del Trabajo (fase 4.5). */
export type ActaFiniquito = {
  id: number
  movimiento_personal_id: number
  numero: string
  fecha: string
  valor_pagado: string
  observacion: string | null
  documento_nombre: string | null
  tiene_documento: boolean
}

/** La terminación del contrato de un obrero, ya registrada (fase 4.5). */
export type TerminacionObrero = {
  movimiento_id: number
  codigo_registro: string | null
  servidor: {
    id: number
    nombre: string | null
    segundo_nombre: string | null
    apellido: string | null
    segundo_apellido: string | null
    cedula: string | null
  } | null
  causal: string | null
  causal_etiqueta: string | null
  fecha_efectiva: string | null
  /** El total de la liquidación referencial; null si la causal no tiene rubros del Código. */
  total_referencial: number | null
  acta: ActaFiniquito | null
  /** El reintegro por despido ineficaz que la deja sin efecto, en trámite o emitido (fase 4.5b). */
  reintegro: { id: number; codigo_registro: string | null; estado: EstadoAccionPersonal } | null
}

/** «Apellidos Nombres», como lo enseñan los listados. */
export function nombreDelObrero(s: TerminacionObrero['servidor']): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre].filter(Boolean).join(' ') || '—'
}

export type ActaFiniquitoData = {
  numero: string
  fecha: string
  valor_pagado: number
  observacion?: string | null
  archivo?: File | null
}

export const terminacionObreroService = {
  listar: (sinActa = false) =>
    api
      .get<ApiResponse<TerminacionObrero[]>>('/expediente/terminaciones-obreros', {
        params: sinActa ? { sin_acta: 1 } : undefined,
      })
      .then((r) => r.data.datos),

  /** Registra o corrige el acta; el PDF es opcional. */
  guardarActa: (movimientoId: number, data: ActaFiniquitoData) => {
    const datos = new FormData()
    datos.append('numero', data.numero)
    datos.append('fecha', data.fecha)
    datos.append('valor_pagado', String(data.valor_pagado))
    if (data.observacion) datos.append('observacion', data.observacion)
    if (data.archivo) datos.append('archivo', data.archivo)

    // La instancia de axios fija JSON: sin esto el PDF llegaba como texto.
    return api
      .post<ApiResponse<ActaFiniquito>>(`/expediente/movimientos/${movimientoId}/acta-finiquito`, datos, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data.datos)
  },

  descargarActa: (movimientoId: number) =>
    api
      .get(`/expediente/movimientos/${movimientoId}/acta-finiquito/documento`, { responseType: 'blob' })
      .then((r) => r.data as Blob),
}
