import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface FemoConstantesVitales {
  temperatura_c?:        number | null
  presion_sistolica?:    number | null
  presion_diastolica?:   number | null
  frecuencia_cardiaca?:  number | null
  frecuencia_respiratoria?: number | null
  saturacion_oxigeno?:  number | null
  peso_kg?:             number | null
  talla_cm?:            number | null
  imc?:                 number | null
  glucosa?:             number | null
}

export interface FemoAntecedente {
  id?:               number
  tipo:              string
  descripcion:       string
  fecha_aproximada?: number | null
}

export interface FemoFactorRiesgo {
  id?:                number
  ficha_actividad_id?: number | null
  categoria:          string
  factor:             string
  presente:           boolean
  medida_preventiva?: string | null
}

export interface FemoFichaActividad {
  id?:                   number
  puesto_actividad_id?:  number | null
  actividad:             string
  medida_preventiva?:    string | null
  orden?:                number | null
  factores_riesgo?:      FemoFactorRiesgo[]
}

export interface FemoDiagnostico {
  id?:                   number
  diagnostico_cie10_id:  number
  tipo:                  'presuntivo' | 'definitivo'
  orden:                 number
  diagnostico?: {
    codigo:      string
    descripcion: string
  }
}

export interface FemoExamen {
  id?:            number
  nombre_examen:  string
  resultado?:     string | null
  fecha_examen?:  string | null
  tipo:           'laboratorio' | 'imagen' | 'otro'
}

export interface FemoEmpleoAnterior {
  id?:                       number
  centro_trabajo:            string
  actividades_desempenadas?: string | null
  fecha_inicio?:             string | null
  fecha_fin?:                string | null
  observaciones?:            string | null
  tipo_evento_laboral?:      'ninguno' | 'incidente' | 'accidente' | 'enfermedad_profesional'
  calificado_iess?:          boolean | null
  fecha_evento?:             string | null
  especificar?:              string | null
}

export interface FemoExamenFisicoItem {
  id?:          number
  region:       string
  item:         string
  normal:       boolean
  observacion?: string | null
}

export interface FemoAntecedenteReproductivo {
  fecha_ultima_menstruacion?: string | null
  gestas?:                    number | null
  partos?:                    number | null
  cesareas?:                  number | null
  abortos?:                   number | null
  usa_metodo_planificacion?:  'si' | 'no' | 'no_responde' | null
  metodo_planificacion_cual?: string | null
  examenes_realizados?:       string | null
  examenes_tiempo_anios?:     number | null
}

export interface FemoConsumoSustancia {
  id?:                        number
  sustancia:                  'tabaco' | 'alcohol' | 'otra'
  sustancia_otra_detalle?:    string | null
  tiempo_consumo_meses?:      number | null
  ex_consumidor?:             boolean
  tiempo_abstinencia_meses?:  number | null
  no_consume?:                boolean
}

export interface FichaSaludOcupacional {
  id:                            number
  servidor_id?:                  number | null
  postulante_id?:                number | null
  evaluador_id:                  number
  accidente_trabajo_id?:         number | null
  numero_archivo?:               string | null
  fecha_evaluacion:              string
  tipo_ficha:                    string
  puesto_trabajo?:               string | null
  puesto_trabajo_ciuo?:          string | null
  fecha_ingreso_trabajo?:        string | null
  grupo_embarazada:              boolean
  grupo_discapacidad:            boolean
  porcentaje_discapacidad?:      string | null
  aptitud:                       string
  restricciones?:                string | null
  observaciones?:                string | null
  enfermedad_actual?:            string | null
  recomendaciones?:              string | null
  tratamiento?:                  string | null
  condicion_relacionada_trabajo?: boolean | null
  observacion_retiro?:           string | null
  actividad_extralaboral_descripcion?: string | null
  actividad_extralaboral_fecha?: string | null
  se_realiza_evaluacion_retiro?: boolean | null
  actividad_fisica_cual?:        string | null
  actividad_fisica_tiempo?:      string | null
  medicacion_habitual_cual?:     string | null
  medicacion_habitual_cantidad?: string | null
  servidor?: {
    id:       number
    nombre:   string
    apellido: string
    cedula?:  string
  }
  postulante?: {
    id:        number
    cedula:    string
    nombres:   string
    apellidos: string
  }
  evaluador?: {
    servidor?: { nombre: string; apellido: string }
  }
  constantes_vitales?:       FemoConstantesVitales | null
  antecedentes?:             FemoAntecedente[]
  factores_riesgo?:          FemoFactorRiesgo[]
  actividades?:              FemoFichaActividad[]
  diagnosticos?:             FemoDiagnostico[]
  examenes?:                 FemoExamen[]
  empleos_anteriores?:       FemoEmpleoAnterior[]
  examen_fisico?:            FemoExamenFisicoItem[]
  antecedente_reproductivo?: FemoAntecedenteReproductivo | null
  consumo_sustancias?:       FemoConsumoSustancia[]
}

export interface CrearFemoData {
  ficha: {
    servidor_id?:             number | null
    postulante_id?:           number | null
    accidente_trabajo_id?:    number | null
    numero_archivo?:          string | null
    fecha_evaluacion:         string
    tipo_ficha:               string
    aptitud:                  string
    puesto_trabajo?:          string | null
    puesto_trabajo_ciuo?:     string | null
    fecha_ingreso_trabajo?:   string | null
    grupo_embarazada?:        boolean
    grupo_discapacidad?:      boolean
    porcentaje_discapacidad?: string | null
    restricciones?:           string | null
    observaciones?:           string | null
    enfermedad_actual?:       string | null
    recomendaciones?:         string | null
    tratamiento?:             string | null
    condicion_relacionada_trabajo?: boolean | null
    observacion_retiro?:      string | null
    actividad_extralaboral_descripcion?: string | null
    actividad_extralaboral_fecha?: string | null
    se_realiza_evaluacion_retiro?: boolean | null
    actividad_fisica_cual?:   string | null
    actividad_fisica_tiempo?: string | null
    medicacion_habitual_cual?: string | null
    medicacion_habitual_cantidad?: string | null
  }
  constantes_vitales?:       FemoConstantesVitales | null
  antecedentes?:             Omit<FemoAntecedente, 'id'>[]
  factores_riesgo?:          Omit<FemoFactorRiesgo, 'id'>[]
  actividades?:              Omit<FemoFichaActividad, 'id' | 'factores_riesgo'>[]
  diagnosticos?:             Omit<FemoDiagnostico, 'id' | 'diagnostico'>[]
  examenes?:                 Omit<FemoExamen, 'id'>[]
  empleos_anteriores?:       Omit<FemoEmpleoAnterior, 'id'>[]
  examen_fisico?:            Omit<FemoExamenFisicoItem, 'id'>[]
  antecedente_reproductivo?: FemoAntecedenteReproductivo | null
  consumo_sustancias?:       Omit<FemoConsumoSustancia, 'id'>[]
}

export const femoService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<FichaSaludOcupacional>>>(
      '/dispensario/fichas-sso', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<FichaSaludOcupacional>>(
      `/dispensario/fichas-sso/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearFemoData) =>
    api.post<ApiResponse<FichaSaludOcupacional>>(
      '/dispensario/fichas-sso', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<CrearFemoData>) =>
    api.patch<ApiResponse<FichaSaludOcupacional>>(
      `/dispensario/fichas-sso/${id}`, data
    ).then(r => r.data.datos),

  descargarPdf: (id: number) =>
    api.get(`/dispensario/fichas-sso/${id}/pdf`, {
      responseType: 'blob',
    }).then(r => r.data as Blob),
}

export {
  TIPO_FICHA_OPTIONS,
  APTITUD_OPTIONS,
  APTITUD_COLORS,
} from './femoOptions'
