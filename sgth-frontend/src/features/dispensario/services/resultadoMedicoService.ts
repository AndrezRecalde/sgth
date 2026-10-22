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
    id:         number
    usuario_ti?: string
    servidor?: {
      nombre:   string
      apellido: string
    } | null
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

/** Icono por tipo de resultado. Nombre de @tabler/icons-react, como en el menú. */
export const TIPO_RESULTADO_ICONS: Record<string, string> = {
  laboratorio:        'IconFlask',
  imagen:             'IconPhoto',
  ecografia:          'IconWaveSine',
  rayos_x:            'IconRadioactive',
  electrocardiograma: 'IconHeartRateMonitor',
  otro:               'IconFileText',
}

export const resultadoMedicoService = {
  /** Siempre de un paciente: el servidor rechaza el listado sin él. */
  listar: (params: {
    historia_clinica_id:  number
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

  /**
   * El archivo, por la API y con la sesión. Vive en el disco privado: ya no
   * hay una dirección de `/storage` que lo sirva a quien la tenga.
   */
  descargar: (id: number) =>
    api.get<Blob>(
      `/dispensario/resultados-medicos/${id}/archivo`,
      { responseType: 'blob' }
    ).then(r => r.data),

  eliminar: (id: number) =>
    api.delete<ApiResponse<unknown>>(
      `/dispensario/resultados-medicos/${id}`
    ).then(r => r.data.datos),
}
