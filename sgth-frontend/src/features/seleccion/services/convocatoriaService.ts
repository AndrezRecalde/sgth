import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface Postulante {
  id:                      number
  convocatoria_id:         number
  cedula:                  string
  nombres:                 string
  segundo_nombre?:         string | null
  apellidos:               string
  segundo_apellido?:       string | null
  correo:                  string
  telefono?:               string | null
  genero?:                 string | null
  estado_civil?:           string | null
  fecha_nacimiento?:       string | null
  tipo_sangre?:            string | null
  provincia_nacimiento_id?: number | null
  canton_nacimiento_id?:   number | null
  cv_ruta?:                string | null
  estado:                  string
  evaluacion?:             EvaluacionSeleccion | null
  documentos?:             DocumentoPostulante[]
}

export interface EvaluacionSeleccion {
  id:                number
  postulante_id:     number
  puntaje_meritos:   number
  puntaje_oposicion: number
  puntaje_total:     number
  evaluador_id:      number
}

export interface DocumentoPostulante {
  id:              number
  postulante_id:   number
  tipo:            string
  nombre_archivo:  string
  ruta:            string
  extension?:      string | null
  tamano_bytes?:   number | null
}

export interface Convocatoria {
  id:             number
  puesto_id:      number
  codigo:         string
  titulo:         string
  descripcion:    string
  bases_concurso?: Record<string, unknown> | null
  fecha_inicio:   string
  fecha_fin:      string
  tipo:           string
  vacantes:       number
  estado:         string
  puesto?: {
    id:    number
    cargo?: { nombre: string }
    unidad_administrativa?: { nombre: string }
    grupo_ocupacional?:     { nombre?: string; rmu?: number }
    actividades_activas?:   { id: number; descripcion: string; orden: number }[]
  }
  postulantes?: Postulante[]
}

export interface CrearConvocatoriaData {
  puesto_id:       number
  titulo:          string
  descripcion:     string
  bases_concurso?: Record<string, unknown> | null
  fecha_inicio:    string
  fecha_fin:       string
  tipo:            'interna' | 'externa' | 'mixta'
  vacantes:        number
}

export const ESTADO_CONVOCATORIA_OPTIONS = [
  { value: 'borrador',              label: 'Borrador'              },
  { value: 'publicada',             label: 'Publicada'             },
  { value: 'en_proceso',            label: 'En proceso'            },
  { value: 'en_evaluacion_medica',  label: 'En evaluación médica'  },
  { value: 'finalizada',            label: 'Finalizada'            },
  { value: 'cerrada',               label: 'Cerrada'               },
  { value: 'desierta',              label: 'Desierta'              },
]

export const ESTADO_CONVOCATORIA_COLORS: Record<string, string> = {
  borrador:             'gray',
  publicada:            'blue',
  en_proceso:           'orange',
  en_evaluacion_medica: 'violet',
  finalizada:           'emerald',
  cerrada:              'emerald',
  desierta:             'red',
}

export const TIPO_CONVOCATORIA_OPTIONS = [
  { value: 'interna', label: 'Interna' },
  { value: 'externa', label: 'Externa' },
  { value: 'mixta',   label: 'Mixta'   },
]

export const ESTADO_POSTULANTE_OPTIONS = [
  { value: 'inscrito',           label: 'Inscrito'            },
  { value: 'en_evaluacion',      label: 'En evaluación'       },
  { value: 'aprobado',           label: 'Aprobado'            },
  { value: 'reprobado',          label: 'Reprobado'           },
  { value: 'seleccionado',       label: 'Seleccionado'        },
  { value: 'ganador_potencial',  label: 'En evaluación médica'},
  { value: 'no_seleccionado',    label: 'No seleccionado'     },
  { value: 'lista_espera',       label: 'Lista de espera'     },
  { value: 'incorporado',        label: 'Incorporado'         },
]

export const convocatoriaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<Convocatoria>>>(
      '/seleccion/convocatorias', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Convocatoria>>(
      `/seleccion/convocatorias/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearConvocatoriaData) =>
    api.post<ApiResponse<Convocatoria>>(
      '/seleccion/convocatorias', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<CrearConvocatoriaData & { estado: string }>) =>
    api.patch<ApiResponse<Convocatoria>>(
      `/seleccion/convocatorias/${id}`, data
    ).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${id}`
    ).then(r => r.data.datos),

  publicar: (id: number) =>
    api.patch<ApiResponse<Convocatoria>>(
      `/seleccion/convocatorias/${id}/publicar`
    ).then(r => r.data.datos),

  listarPostulantes: (convocatoriaId: number) =>
    api.get<ApiResponse<Postulante[]>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes`
    ).then(r => r.data.datos),

  inscribirPostulante: (
    convocatoriaId: number,
    data: Pick<Postulante, 'cedula' | 'nombres' | 'apellidos' | 'correo' | 'telefono'>
  ) =>
    api.post<ApiResponse<Postulante>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes`, data
    ).then(r => r.data.datos),

  eliminarPostulante: (convocatoriaId: number, postulanteId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}`
    ).then(r => r.data.datos),

  subirDocumento: (
    convocatoriaId: number,
    postulanteId: number,
    formData: FormData
  ) =>
    api.post<ApiResponse<DocumentoPostulante>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}/documentos`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    ).then(r => r.data.datos),

  eliminarDocumento: (
    convocatoriaId: number,
    postulanteId: number,
    documentoId: number
  ) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}/documentos/${documentoId}`
    ).then(r => r.data.datos),

  confirmarGanador: (convocatoriaId: number) =>
    api.post<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/confirmar-ganador`
    ).then(r => r.data),
}
