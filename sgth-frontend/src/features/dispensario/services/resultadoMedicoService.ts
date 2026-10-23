import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface ResultadoMedico {
  id:                   number
  historia_clinica_id:  number
  consulta_medica_id?:  number | null
  /** La consulta a la que pertenece, si la tiene: solo su fecha. */
  consulta_medica?: {
    id:             number
    fecha_consulta: string
  } | null
  orden_examen_id?:     number | null
  /** La orden de la que vino, si vino de una. */
  orden_examen?: {
    id:             number
    folio:          string
    estado:         string
    solicitado_por?: number
  } | null
  /** Los exámenes de esa orden que cubre el archivo. */
  items_orden?: {
    id:      number
    examen?: { id: number; nombre: string } | null
  }[]
  /** El paciente, para darle un turno de control desde un resultado alterado. */
  historia_clinica?: {
    id:                number
    servidor_id:       number | null
    carga_familiar_id: number | null
  } | null
  tipo:                 string
  descripcion:          string
  archivo:              string
  fecha_resultado:      string
  subido_por?:          PersonaResultado | null
  /** La lectura del médico (D3). Lo subido antes de la fase 4 está revisado sin interpretación. */
  estado_revision:      'por_revisar' | 'revisado'
  interpretacion:       Interpretacion | null
  comentario_medico:    string | null
  revisado_en:          string | null
  /** Un crítico que revisó otro médico, hasta que lo ve quien pidió el examen. */
  critico_visto_en:     string | null
  revisor?:             PersonaResultado | null
}

export type Interpretacion = 'normal' | 'alterado' | 'critico'

export interface PersonaResultado {
  id:          number
  usuario_ti?: string
  servidor?: {
    nombre:   string
    apellido: string
  } | null
}

export interface RevisarResultadoData {
  interpretacion:    Interpretacion
  comentario_medico: string | null
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

  /** La lectura del médico: normal, alterado o crítico, con su comentario. */
  revisar: (id: number, data: RevisarResultadoData) =>
    api.patch<ApiResponse<ResultadoMedico>>(
      `/dispensario/resultados-medicos/${id}/revisar`, data
    ).then(r => r.data.datos),

  /** Quien pidió el examen da por visto un crítico que revisó otro. */
  enterado: (id: number) =>
    api.patch<ApiResponse<ResultadoMedico>>(
      `/dispensario/resultados-medicos/${id}/enterado`
    ).then(r => r.data.datos),
}

/** Etiqueta y tono de la lectura del médico. */
export const INTERPRETACION: Record<Interpretacion, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
  normal:   { label: 'Normal',   tone: 'success' },
  alterado: { label: 'Alterado', tone: 'warning' },
  critico:  { label: 'Crítico',  tone: 'danger'  },
}
