import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface ResultadoMedico {
  id:                   number
  historia_clinica_id:  number
  consulta_medica_id?:  number | null
  tipo:                 string
  descripcion:          string
  archivo:              string
  fecha_resultado:      string
  subido_por?: {
    id:      number
    nombre:  string
    apellido: string
  } | null
}

export const TIPO_RESULTADO_OPTIONS = [
  { value: 'laboratorio',       label: 'Laboratorio'         },
  { value: 'imagen',            label: 'Imagen diagnóstica'  },
  { value: 'ecografia',         label: 'Ecografía'           },
  { value: 'rayos_x',          label: 'Rayos X'             },
  { value: 'electrocardiograma',label: 'Electrocardiograma'  },
  { value: 'otro',              label: 'Otro'                },
]

export const TIPO_RESULTADO_ICONS: Record<string, string> = {
  laboratorio:        '🧪',
  imagen:             '🖼️',
  ecografia:          '📡',
  rayos_x:           '☢️',
  electrocardiograma: '💓',
  otro:               '📄',
}

export const resultadoMedicoService = {
  listar: (params: {
    historia_clinica_id?: number
    consulta_medica_id?:  number
  }) =>
    api.get<ApiResponse<ResultadoMedico[]>>(
      '/dispensario/resultados-medicos',
      { params }
    ).then(r => r.data.datos),

  subir: (formData: FormData) =>
    api.post<ApiResponse<ResultadoMedico>>(
      '/dispensario/resultados-medicos',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    ).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<unknown>>(
      `/dispensario/resultados-medicos/${id}`
    ).then(r => r.data.datos),
}
