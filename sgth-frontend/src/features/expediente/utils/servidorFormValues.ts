import type { DefaultValues } from 'react-hook-form'
import type { ServidorConRelaciones } from '@/types/api'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'

/**
 * Valores del formulario de la ficha: en blanco y a partir de un servidor.
 *
 * Género y estado civil no tienen valor por defecto. Arrancaban en
 * «Masculino» y «Soltero/a»: en el alta, quien no los tocaba registraba así a
 * cualquiera, y en la edición una ficha que no los tenía los mostraba como si
 * estuvieran guardados. Como el modal envía solo lo que se tocó, guardar no los
 * enviaba nunca y la pestaña Personal seguía diciendo «—» (2026-10-19).
 */

export const BLANK_FORM_VALUES: DefaultValues<ServidorBasicoFormData> = {
  nombre:           '',
  segundo_nombre:   '',
  apellido:         '',
  segundo_apellido: '',
  cedula:           '',
  fecha_nacimiento: '',
  tipo_sangre:             null,
  es_extranjero:           false,
  provincia_nacimiento_id: null,
  canton_nacimiento_id:    null,
  nacionalidad:            '',
  pais_origen:             '',
  numero_papeleta_votacion: '',
  pasaporte_numero:        '',
  pasaporte_vencimiento:   null,
  telefono_celular:      '',
  telefono_convencional: '',
  correo_personal:       '',
  codigo_medico:         '',
  direccion_domicilio:   '',
  contacto_emergencia_nombre:     '',
  contacto_emergencia_parentesco: '',
  contacto_emergencia_telefono:   '',
}

const soloFecha = (v?: string | null) => (v ? v.split('T')[0] : null)

export function mapServidorToFormValues(
  servidor: ServidorConRelaciones,
): DefaultValues<ServidorBasicoFormData> {
  return {
    nombre:           servidor.nombre  ?? '',
    segundo_nombre:   servidor.segundo_nombre  ?? '',
    apellido:         servidor.apellido ?? '',
    segundo_apellido: servidor.segundo_apellido ?? '',
    cedula:           servidor.cedula ?? '',
    fecha_nacimiento: soloFecha(servidor.fecha_nacimiento) ?? '',
    genero:       (servidor.genero ?? undefined) as ServidorBasicoFormData['genero'] | undefined,
    estado_civil: (servidor.estado_civil ?? undefined) as ServidorBasicoFormData['estado_civil'] | undefined,
    tipo_sangre:             (servidor.tipo_sangre as ServidorBasicoFormData['tipo_sangre']) ?? null,
    es_extranjero:           servidor.es_extranjero ?? false,
    provincia_nacimiento_id: servidor.provincia_nacimiento_id ?? null,
    canton_nacimiento_id:    servidor.canton_nacimiento_id ?? null,
    nacionalidad:            servidor.nacionalidad ?? '',
    pais_origen:             servidor.pais_origen ?? '',
    numero_papeleta_votacion: servidor.numero_papeleta_votacion ?? '',
    pasaporte_numero:        servidor.pasaporte_numero ?? '',
    pasaporte_vencimiento:   soloFecha(servidor.pasaporte_vencimiento),
    telefono_celular:      servidor.telefono_celular ?? '',
    telefono_convencional: servidor.telefono_convencional ?? '',
    correo_personal:       servidor.correo_personal ?? '',
    codigo_medico:         servidor.codigo_medico ?? '',
    direccion_domicilio:   servidor.direccion_domicilio ?? '',
    contacto_emergencia_nombre:     servidor.contacto_emergencia_nombre ?? '',
    contacto_emergencia_parentesco: servidor.contacto_emergencia_parentesco ?? '',
    contacto_emergencia_telefono:   servidor.contacto_emergencia_telefono ?? '',
  }
}

/** Valores del formulario de edición: la ficha más lo editable de antigüedad. */
export function valoresDeEdicion(servidor: ServidorConRelaciones) {
  return {
    ...mapServidorToFormValues(servidor),
    ...mapServidorToLaboralValues(servidor),
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
  ],
  [
    'telefono_celular', 'telefono_convencional', 'correo_personal', 'codigo_medico',
    'direccion_domicilio', 'numero_papeleta_votacion', 'pasaporte_numero', 'pasaporte_vencimiento',
    'contacto_emergencia_nombre', 'contacto_emergencia_parentesco', 'contacto_emergencia_telefono',
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
