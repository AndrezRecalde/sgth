import type { DefaultValues } from 'react-hook-form'
import type { CargaFamiliar } from '@/types/api'
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
  persona_con_discapacidad: false,
  posee_enfermedad_catastrofica: false,
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
    persona_con_discapacidad: carga.persona_con_discapacidad ?? false,
    posee_enfermedad_catastrofica: carga.posee_enfermedad_catastrofica ?? false,
    observaciones: carga.observaciones ?? '',
  }
}
