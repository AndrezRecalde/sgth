import type { DefaultValues } from 'react-hook-form'
import {
  vinculacionInicialSchema, type VinculacionInicialFormData,
} from '../schemas/vinculacionInicial.schema'
import { CAMPOS_POR_PASO } from './servidorFormValues'

/**
 * Unidad, puesto y remuneración arrancan sin valor: el esquema los exige al
 * enviar, pero los valores iniciales de un formulario son parciales por
 * definición, de ahí `DefaultValues`.
 */
export const VINCULACION_EN_BLANCO: DefaultValues<VinculacionInicialFormData> = {
  nombre: '', segundo_nombre: '', apellido: '', segundo_apellido: '', cedula: '',
  fecha_nacimiento: '',
  genero: 'masculino',
  estado_civil: 'soltero',
  tipo_sangre: null,
  es_extranjero: false,
  provincia_nacimiento_id: null,
  canton_nacimiento_id: null,
  nacionalidad: '', pais_origen: '',
  numero_papeleta_votacion: '', pasaporte_numero: '',
  telefono_celular: '', telefono_convencional: '',
  correo_personal: '', direccion_domicilio: '',
  fecha_ingreso_institucion: null,
  fecha_ingreso_sector_publico: null,
  vinculo: {
    tipo_nombramiento: 'nombramiento_permanente',
    fecha_inicio: '',
    fecha_fin: null,
    numero_contrato: '',
    resolucion_numero: '',
    puede_marcar: true,
  },
}

/** Campos que deben validarse antes de dejar avanzar de paso. */
export const PASO_PERSONAL = [
  'nombre', 'apellido', 'cedula', 'fecha_nacimiento', 'genero', 'estado_civil',
  'es_extranjero', 'provincia_nacimiento_id', 'canton_nacimiento_id',
  'nacionalidad', 'pais_origen',
] as const

/**
 * Todos los campos del formulario, con los del vínculo en la forma en que
 * los nombra el backend al rechazarlos: `vinculo.fecha_inicio`.
 */
export const CAMPOS_VINCULACION: readonly string[] = [
  ...Object.keys(vinculacionInicialSchema.shape).filter((c) => c !== 'vinculo'),
  ...Object.keys(vinculacionInicialSchema.shape.vinculo.shape).map((c) => `vinculo.${c}`),
]

const PASO_CONTACTO: readonly string[] = [
  ...CAMPOS_POR_PASO[1], 'fecha_ingreso_institucion', 'fecha_ingreso_sector_publico',
]

/**
 * El primer paso con alguno de los campos dados, o `null`. Sirve igual para
 * los errores del esquema, que llegan como `vinculo` a secas, y para los del
 * backend, que llegan como `vinculo.fecha_inicio`.
 */
export function pasoDeVinculacion(campos: string[]): number | null {
  if (campos.some((c) => (CAMPOS_POR_PASO[0] as readonly string[]).includes(c))) return 0
  if (campos.some((c) => PASO_CONTACTO.includes(c))) return 1
  if (campos.some((c) => c === 'vinculo' || c.startsWith('vinculo.'))) return 2
  return null
}
