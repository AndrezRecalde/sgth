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
  fecha_inscripcion?:      string | null
  estado:                  string
  evaluacion?:             EvaluacionSeleccion | null
  /** La última solicitud al Dispensario: decide si se ofrece «Confirmar incorporación». */
  solicitud_certificacion?: { id: number; estado: string; dictamen?: string | null } | null
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
  tipo_proceso:   'formal' | 'express'
  tipo_nombramiento_previsto?: string | null
  vacantes:       number
  estado:         string
  /** Por qué se declaró desierta o se canceló. */
  motivo_cierre?: string | null
  puesto?: {
    id:    number
    cargo?: { nombre: string }
    unidad_administrativa?: { nombre: string }
    grupo_ocupacional?:     { nombre?: string; rmu?: number }
    actividades_activas?:   { id: number; descripcion: string; orden: number }[]
    regimen_laboral?: string
  }
  postulantes?: Postulante[]
}

export interface CrearConvocatoriaData {
  puesto_id:       number
  titulo:          string
  descripcion:     string
  bases_concurso?: Record<string, unknown> | null
  fecha_inicio?:   string
  fecha_fin?:      string
  tipo:            'interna' | 'externa' | 'mixta'
  tipo_proceso:    'formal' | 'express'
  tipo_nombramiento_previsto?: string
  vacantes:        number
}

// Las opciones y los tonos viven en constants/convocatoria; se reexportan para
// no cambiar las importaciones de todo el módulo.
export * from '../constants/convocatoria'

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

  // Sin `estado`: el PATCH ya no lo acepta (2026-10-05) y solo edita borradores.
  actualizar: (id: number, data: Partial<CrearConvocatoriaData>) =>
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
      & Partial<Record<
        'segundo_nombre' | 'segundo_apellido' | 'genero' | 'estado_civil'
        | 'fecha_nacimiento' | 'tipo_sangre' | 'fecha_inscripcion',
        string | null
      >>
      // Solo en contenedores express: el puesto lo trae el aspirante.
      & { puesto_id?: number | null }
  ) =>
    api.post<ApiResponse<Postulante>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes`, data
    ).then(r => r.data.datos),

  eliminarPostulante: (convocatoriaId: number, postulanteId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}`
    ).then(r => r.data.datos),
  // Los documentos del postulante viven en documentoPostulanteService.
}
