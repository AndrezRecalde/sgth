import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface DiagnosticoCie10 {
  id:          number
  codigo:      string
  descripcion: string
  categoria?:  string | null
}

interface MetaBusquedaCie10 {
  total:     number
  mostrados: number
  hay_mas:   boolean
}

export interface BusquedaCie10 {
  resultados: DiagnosticoCie10[]
  /** Cuántos códigos coinciden en total, no cuántos vienen en la lista. */
  total:      number
  /** Hay más de los que caben: la lista está recortada. */
  hayMas:     boolean
}

export const cie10Service = {
  buscar: (q: string): Promise<BusquedaCie10> =>
    api.get<ApiResponse<DiagnosticoCie10[], MetaBusquedaCie10>>(
      '/dispensario/cie10/buscar',
      { params: { q } }
    ).then(r => {
      const resultados = r.data.datos ?? []

      return {
        resultados,
        total:  r.data.meta?.total ?? resultados.length,
        hayMas: r.data.meta?.hay_mas ?? false,
      }
    }),
}
