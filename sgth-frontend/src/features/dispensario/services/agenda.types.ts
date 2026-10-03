import type { Triaje } from './triajeService'

export type EstadoAgenda =
  | 'en_espera'
  | 'en_sala'
  | 'en_consulta'
  | 'atendido'
  | 'no_presentado'
  // Femenino: es lo que escribe el backend (ver constants/turnos.ts).
  | 'cancelada'

export interface AgendaMedica {
  id:                           number
  folio?:                       string | null
  medico_id:                    number
  servidor_id?:                 number | null
  carga_familiar_id?:           number | null
  tipo_atencion:                'medicina_general' | 'odontologia'
  fecha:                        string
  hora_inicio?:                 string | null
  hora_fin?:                    string | null
  registrado_en?:               string | null
  estado:                       EstadoAgenda
  requiere_triaje?:             boolean
  motivo_solicitud?:            string | null
  historia_clinica_id?:         number | null
  marcado_no_presentado_en?:    string | null
  reactivado_en?:               string | null
  medico?: {
    id: number
    nombre_completo?: string
    usuario_ti?: string
  }
  servidor?: {
    id: number
    nombre: string
    apellido: string
    /** Para no prometer una alerta que el backend no da a un menor. */
    fecha_nacimiento?: string | null
  } | null
  carga_familiar?: {
    id: number
    nombres: string
    apellidos: string
    fecha_nacimiento?: string | null
  } | null
  /**
   * Lo carga el listado de la cola. Estaba como `unknown`, así que su nivel de
   * alerta no se podía leer sin aserciones.
   */
  triaje?: Triaje | null
  consulta_medica?: {
    id: number
    tipo_atencion?: string
    tipo_diagnostico?: string
    diagnostico_detallado?: string
  } | null
}

export interface CrearAgendaData {
  medico_id:          number
  servidor_id?:       number | null
  carga_familiar_id?: number | null
  tipo_atencion:      'medicina_general' | 'odontologia'
  motivo_solicitud?:  string | null
  requiere_triaje:    boolean
}
