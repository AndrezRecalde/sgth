import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface DiagnosticoCie10 {
  id:          number
  codigo:      string
  descripcion: string
  categoria?:  string | null
}

export const cie10Service = {
  buscar: (q: string) =>
    api.get<ApiResponse<DiagnosticoCie10[]>>(
      '/dispensario/cie10/buscar',
      { params: { q } }
    ).then(r => r.data.datos),
}
