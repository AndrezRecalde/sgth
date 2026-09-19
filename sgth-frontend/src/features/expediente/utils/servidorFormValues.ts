import type { ServidorConRelaciones } from '@/types/api'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'

/** Valores del formulario de la ficha: en blanco y a partir de un servidor. */

export const BLANK_FORM_VALUES: ServidorBasicoFormData = {
  nombre:           '',
  segundo_nombre:   '',
  apellido:         '',
  segundo_apellido: '',
  cedula:           '',
  fecha_nacimiento: '',
  genero:           'masculino',
  estado_civil:     'soltero',
  tipo_sangre:             null,
  es_extranjero:           false,
  provincia_nacimiento_id: null,
  canton_nacimiento_id:    null,
  nacionalidad:            '',
  pais_origen:             '',
  numero_papeleta_votacion: '',
  pasaporte_numero:        '',
  tiene_discapacidad:            false,
  tiene_enfermedad_catastrofica: false,
  telefono_celular:      '',
  telefono_convencional: '',
  correo_personal:       '',
  codigo_medico:         '',
  direccion_domicilio:   '',
}

const soloFecha = (v?: string | null) => (v ? v.split('T')[0] : null)

export function mapServidorToFormValues(servidor: ServidorConRelaciones): ServidorBasicoFormData {
  return {
    nombre:           servidor.nombre  ?? '',
    segundo_nombre:   servidor.segundo_nombre  ?? '',
    apellido:         servidor.apellido ?? '',
    segundo_apellido: servidor.segundo_apellido ?? '',
    cedula:           servidor.cedula ?? '',
    fecha_nacimiento: soloFecha(servidor.fecha_nacimiento) ?? '',
    genero:           (servidor.genero as ServidorBasicoFormData['genero'])
      ?? 'masculino',
    estado_civil: (servidor.estado_civil as ServidorBasicoFormData['estado_civil'])
      ?? 'soltero',
    tipo_sangre:             (servidor.tipo_sangre as ServidorBasicoFormData['tipo_sangre']) ?? null,
    es_extranjero:           servidor.es_extranjero ?? false,
    provincia_nacimiento_id: servidor.provincia_nacimiento_id ?? null,
    canton_nacimiento_id:    servidor.canton_nacimiento_id ?? null,
    nacionalidad:            servidor.nacionalidad ?? '',
    pais_origen:             servidor.pais_origen ?? '',
    numero_papeleta_votacion: servidor.numero_papeleta_votacion ?? '',
    pasaporte_numero:        servidor.pasaporte_numero ?? '',
    tiene_discapacidad:            servidor.tiene_discapacidad ?? false,
    tiene_enfermedad_catastrofica: servidor.tiene_enfermedad_catastrofica ?? false,
    telefono_celular:      servidor.telefono_celular ?? '',
    telefono_convencional: servidor.telefono_convencional ?? '',
    correo_personal:       servidor.correo_personal ?? '',
    codigo_medico:         servidor.codigo_medico ?? '',
    direccion_domicilio:   servidor.direccion_domicilio ?? '',
  }
}

export function mapServidorToLaboralValues(servidor?: ServidorConRelaciones | null): ServidorLaboralFormData {
  return {
    fecha_ingreso_sector_publico: soloFecha(servidor?.fecha_ingreso_sector_publico),
    fecha_nombramiento:           soloFecha(servidor?.fecha_nombramiento),
  }
}

/**
 * Los campos de cada paso del asistente, en orden. Sirven para validar solo
 * el paso visible al pulsar «Siguiente» y para volver al paso de un campo con
 * error: antes el paso «Contacto» no se validaba y un correo inválido dejaba
 * «Actualizar» sin hacer nada en el paso siguiente, sin decir por qué.
 */
export const CAMPOS_POR_PASO = [
  [
    'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'cedula',
    'fecha_nacimiento', 'genero', 'estado_civil', 'tipo_sangre', 'es_extranjero',
    'provincia_nacimiento_id', 'canton_nacimiento_id', 'nacionalidad', 'pais_origen',
    'tiene_discapacidad', 'tiene_enfermedad_catastrofica',
  ],
  [
    'telefono_celular', 'telefono_convencional', 'correo_personal', 'codigo_medico',
    'direccion_domicilio', 'numero_papeleta_votacion', 'pasaporte_numero',
  ],
  ['fecha_ingreso_sector_publico', 'fecha_nombramiento'],
] as const satisfies readonly (readonly (keyof (ServidorBasicoFormData & ServidorLaboralFormData))[])[]

/** El primer paso que contiene alguno de los campos dados, o `null`. */
export function pasoConError(campos: string[]): number | null {
  const paso = CAMPOS_POR_PASO.findIndex((grupo) =>
    grupo.some((c) => campos.includes(c)),
  )
  return paso === -1 ? null : paso
}
