import type { DefaultValues } from 'react-hook-form'
import type { CargaFamiliar, TipoParentesco } from '@/types/api'
import type { CargaFamiliarFormData } from '../schemas/cargaFamiliar.schema'

export const PARENTESCO_OPTIONS = [
  { value: 'conyugue', label: 'Cónyuge / Conviviente' },
  { value: 'hijo', label: 'Hijo/a' },
]

/** Los valores de `servidores.genero`, sin `otro`: aquí es el sexo. */
export const SEXO_OPTIONS = [
  { value: 'femenino', label: 'Femenino' },
  { value: 'masculino', label: 'Masculino' },
]

/**
 * El parentesco en la tabla, corto: «Cónyuge / Conviviente» no cabe en la
 * columna sin empujar el estado fuera de la vista.
 *
 * Vive aquí y tipado por el enum. La tabla tenía su propio mapa, con cinco
 * parentescos (padre, madre, hermano, otro y un «conyuge» bien escrito) que
 * el backend no admite y que ninguna fila tenía; con `Record<TipoParentesco>`
 * el compilador rechaza uno que sobre o que falte. La columna guarda
 * «conyugue» desde el primer día; la etiqueta lo escribe bien.
 */
export const PARENTESCO_LABELS: Record<TipoParentesco, string> = {
  conyugue: 'Cónyuge',
  hijo: 'Hijo/a',
}

/** El sexo en la tabla, con las etiquetas del formulario. */
export const SEXO_LABELS: Record<string, string> = Object.fromEntries(
  SEXO_OPTIONS.map((o) => [o.value, o.label]),
)

/**
 * El formulario vacío. El sexo se omite: no hay uno por defecto, y en un
 * familiar registrado antes de existir el campo queda vacío para que se
 * complete al editarlo.
 */
export const CARGA_FAMILIAR_VACIA: DefaultValues<CargaFamiliarFormData> = {
  cedula: '',
  nombres: '',
  apellidos: '',
  parentesco: 'hijo',
  fecha_nacimiento: '',
  observaciones: '',
}

/** Un familiar registrado, como valores del formulario para editarlo. */
export function valoresDeCarga(carga: CargaFamiliar): DefaultValues<CargaFamiliarFormData> {
  return {
    cedula: carga.cedula ?? '',
    nombres: carga.nombres ?? '',
    apellidos: carga.apellidos ?? '',
    parentesco: carga.parentesco ?? 'hijo',
    fecha_nacimiento: carga.fecha_nacimiento?.split('T')[0] ?? '',
    genero: carga.genero ?? undefined,
    observaciones: carga.observaciones ?? '',
  }
}
