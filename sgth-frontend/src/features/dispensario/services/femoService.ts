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
  categoria:          string
  factor:             string
  presente:           boolean
  medida_preventiva?: string | null
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
}

export interface FichaSaludOcupacional {
  id:                            number
  servidor_id:                   number
  evaluador_id:                  number
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
  servidor?: {
    id:       number
    nombre:   string
    apellido: string
    cedula?:  string
  }
  evaluador?: {
    servidor?: { nombre: string; apellido: string }
  }
  constantes_vitales?: FemoConstantesVitales | null
  antecedentes?:       FemoAntecedente[]
  factores_riesgo?:    FemoFactorRiesgo[]
  diagnosticos?:       FemoDiagnostico[]
  examenes?:           FemoExamen[]
  empleos_anteriores?: FemoEmpleoAnterior[]
}

export interface CrearFemoData {
  ficha: {
    servidor_id:              number
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
  }
  constantes_vitales?:  FemoConstantesVitales | null
  antecedentes?:        Omit<FemoAntecedente, 'id'>[]
  factores_riesgo?:     Omit<FemoFactorRiesgo, 'id'>[]
  diagnosticos?:        Omit<FemoDiagnostico, 'id' | 'diagnostico'>[]
  examenes?:            Omit<FemoExamen, 'id'>[]
  empleos_anteriores?:  Omit<FemoEmpleoAnterior, 'id'>[]
}

export const TIPO_FICHA_OPTIONS = [
  { value: 'ingreso',    label: 'Ingreso / Pre-ocupacional' },
  { value: 'periodica',  label: 'Periódica'                 },
  { value: 'reintegro',  label: 'Reintegro'                 },
  { value: 'retiro',     label: 'Retiro'                    },
  { value: 'especial',   label: 'Especial'                  },
]

export const APTITUD_OPTIONS = [
  { value: 'apto',                  label: 'Apto'                    },
  { value: 'apto_con_restricciones',label: 'Apto con restricciones'  },
  { value: 'en_observacion',        label: 'En observación'          },
  { value: 'no_apto',               label: 'No apto'                 },
]

export const APTITUD_COLORS: Record<string, string> = {
  apto:                   'emerald',
  apto_con_restricciones: 'orange',
  en_observacion:         'blue',
  no_apto:                'red',
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
}
