/*
| Los tipos de las solicitudes de certificación médica tal como viajan por el
| API. Aparte del servicio, que pasaba de 270 líneas (la regla 02 marca 100).
*/

export interface SolicitudConstantesVitales {
  id?:                       number
  peso_kg?:                  number | null
  talla_cm?:                 number | null
  perimetro_abdominal_cm?:   number | null
  imc?:                      number | null
  temperatura_c?:            number | null
  presion_sistolica?:        number | null
  presion_diastolica?:       number | null
  frecuencia_cardiaca?:      number | null
  frecuencia_respiratoria?:  number | null
  saturacion_oxigeno?:       number | null
  glucosa?:                  number | null
  observaciones_enfermera?:  string | null
  registrado_en?:            string | null
}

export interface CrearSolicitudSignosVitalesData {
  peso_kg:                  number
  talla_cm:                 number
  temperatura_c:            number
  presion_sistolica:        number
  presion_diastolica:       number
  frecuencia_cardiaca:      number
  frecuencia_respiratoria:  number
  saturacion_oxigeno:       number
  glucosa?:                 number | null
  observaciones_enfermera?: string | null
}


/** Puesto tal como lo expone el detalle de la solicitud. */
export interface PuestoDeSolicitud {
  id: number
  cargo?: {
    id?: number
    nombre: string
    /** Código CIUO-08 del cargo. La ficha FEMO lo hereda, no lo pide. */
    codigo_ciuo?: string | null
  } | null
  unidad_administrativa?: {
    id: number
    nombre: string
  } | null
}


/**
 * Sexo del paciente, para decidir qué bloque reproductivo mostrar.
 *
 * Puede faltar: la columna `genero` no está poblada para toda la plantilla,
 * así que el formulario tiene que comportarse bien cuando llega vacía.
 */
export type SexoPaciente = 'masculino' | 'femenino' | 'otro' | null

export interface SolicitudCertificacion {
  id:                number
  tipo_evento:       string
  origen:            string
  cedula_paciente:   string
  nombres_paciente:  string
  correo_paciente?:  string | null
  puesto_solicitado?: string | null
  estado:            string
  fecha_limite?:     string | null
  created_at:        string
  ficha_femo_id?:      number | null
  dictamen?:           string | null
  observacion_medica?: string | null
  /** Cuándo se retiró la solicitud, si se retiró. */
  cancelada_en?:        string | null
  motivo_cancelacion?:  string | null
  /**
   * La ficha que firmó quien evaluó. Solo llegan la aptitud, sus
   * restricciones y la fecha: `observaciones` es texto clínico y no viaja al
   * Expediente (acuerdo con la UATH del 2026-09-26).
   */
  ficha_salud_ocupacional?: {
    id:               number
    aptitud:          string
    restricciones?:   string | null
    fecha_evaluacion: string
  } | null
  constantes_vitales?: SolicitudConstantesVitales | null
  servidor?: {
    id:      number
    nombre:  string
    apellido: string
    cedula:  string
    genero?: SexoPaciente
    tipo_sangre?: string | null
    /** Lo que consta en el Expediente, para que el FEMO nazca con ello. */
    tiene_discapacidad?: boolean
    discapacidades?: { id: number; porcentaje: string | number }[]
    puesto?: PuestoDeSolicitud | null
    unidad_administrativa?: {
      id:     number
      nombre: string
    } | null
  } | null
  postulante?: {
    id:       number
    nombres:  string
    apellidos: string
    cedula:   string
    correo:   string
    genero?: SexoPaciente
    tipo_sangre?: string | null
    /** En reclutamiento express el puesto lo trae el aspirante, no la convocatoria. */
    puesto?: PuestoDeSolicitud | null
  } | null
  convocatoria?: {
    id:     number
    codigo: string
    titulo: string
    puesto?: PuestoDeSolicitud | null
  } | null
  solicitado_por?: {
    servidor?: {
      nombre:   string
      apellido: string
    } | null
  } | null
}

export interface CrearSolicitudLoteData {
  servidor_ids:   number[]
  tipo_evento:    'periodica' | 'reintegro' | 'retiro'
  fecha_limite?:  string | null
  observaciones?: string | null
}

export interface SolicitudLoteOmitida {
  servidor_id: number
  motivo:      string
}

export interface SolicitudLoteResultado {
  creadas:  SolicitudCertificacion[]
  omitidas: SolicitudLoteOmitida[]
}
