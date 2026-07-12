import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  SeccionCriterio, TipoInput,
} from './criterioService'

export interface PlantillaOpcion {
  id:                     number
  plantilla_criterio_id:  number
  etiqueta:               string
  puntaje:                number
  orden:                  number
}

export interface PlantillaCriterio {
  id:              number
  plantilla_id:    number
  seccion:         SeccionCriterio
  nombre:          string
  descripcion?:    string | null
  puntaje_maximo:  number
  tipo_input:      TipoInput
  orden:           number
  opciones:        PlantillaOpcion[]
}

export interface PlantillaEvaluacion {
  id:               number
  nombre:           string
  descripcion?:     string | null
  tipo_contrato?:   string | null
  activa:           boolean
  criterios_count?: number
  criterios?:       PlantillaCriterio[]
}

export const TIPO_CONTRATO_PLANTILLA_OPTIONS = [
  { value: 'losep',                  label: 'LOSEP'                         },
  { value: 'codigo_trabajo',         label: 'Código de Trabajo'             },
  { value: 'servicios_profesionales',label: 'Servicios Profesionales'       },
  { value: 'general',                label: 'General (todos los tipos)'     },
]

export const plantillaService = {
  listar: () =>
    api.get<ApiResponse<PlantillaEvaluacion[]>>(
      '/seleccion/plantillas'
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<PlantillaEvaluacion>>(
      `/seleccion/plantillas/${id}`
    ).then(r => r.data.datos),

  crear: (data: {
    nombre:        string
    descripcion?:  string | null
    tipo_contrato?: string | null
  }) =>
    api.post<ApiResponse<PlantillaEvaluacion>>(
      '/seleccion/plantillas', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<{
    nombre:        string
    descripcion:   string
    tipo_contrato: string
    activa:        boolean
  }>) =>
    api.patch<ApiResponse<PlantillaEvaluacion>>(
      `/seleccion/plantillas/${id}`, data
    ).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/plantillas/${id}`
    ).then(r => r.data.datos),

  agregarCriterio: (
    plantillaId: number,
    data: {
      seccion:        SeccionCriterio
      nombre:         string
      descripcion?:   string | null
      puntaje_maximo: number
      tipo_input:     TipoInput
      opciones?:      { etiqueta: string; puntaje: number }[]
    }
  ) =>
    api.post<ApiResponse<PlantillaCriterio>>(
      `/seleccion/plantillas/${plantillaId}/criterios`, data
    ).then(r => r.data.datos),

  eliminarCriterio: (plantillaId: number, criterioId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/plantillas/${plantillaId}/criterios/${criterioId}`
    ).then(r => r.data.datos),

  aplicarAConvocatoria: (
    plantillaId:    number,
    convocatoriaId: number
  ) =>
    api.post<ApiResponse<unknown>>(
      `/seleccion/plantillas/${plantillaId}/aplicar/${convocatoriaId}`
    ).then(r => r.data.datos),
}
