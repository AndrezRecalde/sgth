/**
 * Cómo se clasifica un puesto: su régimen, su rol y su complejidad. Espejan
 * `RolPuesto` y `NivelComplejidadPuesto` del backend.
 *
 * Estaban escritos dentro del formulario, y la misma complejidad salía como
 * «Nivel Bajo» en el formulario, «Bajo» en la tabla de grupos y «Complejidad
 * baja» en el perfil del puesto. Aquí se escriben una vez, con mayúscula solo
 * al principio, como el resto de la aplicación.
 */

/**
 * Espeja `TipoPuesto` del backend (docs/diseno-manual-de-puestos.md, P9). El de
 * plantilla es el del distributivo: tiene plazas, se concursa y cuenta en las
 * vacantes. El de contrato sostiene a los ocasionales y a los de servicios
 * profesionales: no tiene plazas ni es jefatura.
 */
export type TipoPuesto = 'plantilla' | 'contrato'

export const TIPOS_PUESTO: { value: TipoPuesto; label: string }[] = [
  { value: 'plantilla', label: 'De plantilla' },
  { value: 'contrato',  label: 'De contrato' },
]

/** Las modalidades que no ocupan plaza y van en un puesto de contrato [TH D11, D12]. */
const MODALIDADES_DE_CONTRATO = ['servicios_ocasionales', 'servicios_profesionales']

/**
 * El tipo de puesto que admite una modalidad. Espeja
 * `TipoNombramiento::admitePuesto()`: el ocasional y el profesional, en uno de
 * contrato; el resto, en uno de plantilla. El reemplazo de un ausente entra al
 * puesto del titular [TH D13], así que admite los dos y no se filtra (`null`).
 * Sin modalidad, tampoco.
 */
export function tipoDePuestoPara(modalidad?: string | null, esReemplazo = false): TipoPuesto | null {
  if (!modalidad) return null
  if (MODALIDADES_DE_CONTRATO.includes(modalidad)) return esReemplazo ? null : 'contrato'
  return 'plantilla'
}

/**
 * Por qué el puesto elegido no sirve, si no sirve. El selector lo deja a la
 * vista —un puesto que desaparece de la lista se lee como un número—, y el
 * campo lo dice antes de que lo rechace el backend.
 */
export function avisoDelTipoDePuesto(tipoDelPuesto?: TipoPuesto | null, admitido?: TipoPuesto | null): string | undefined {
  if (!tipoDelPuesto || !admitido || tipoDelPuesto === admitido) return undefined

  return admitido === 'contrato'
    ? 'Es un puesto de plantilla; aquí corresponde uno de contrato.'
    : 'Es un puesto de contrato; aquí corresponde uno de plantilla.'
}

/** Lo que el selector avisa cuando solo muestra los puestos de contrato. */
export const SOLO_PUESTOS_DE_CONTRATO = 'Solo los de contrato: los ocasionales y los profesionales no ocupan plaza.'

export type RegimenPuesto = 'losep' | 'codigo_trabajo'

export const REGIMENES_PUESTO: { value: RegimenPuesto; label: string }[] = [
  { value: 'losep',          label: 'LOSEP' },
  { value: 'codigo_trabajo', label: 'Código del Trabajo' },
]

export type RolPuesto =
  | 'dignatario' | 'ejecucion_coordinacion' | 'ejecucion_procesos'
  | 'ejecucion_procesos_apoyo' | 'administrativo' | 'codigo_trabajo'

export const ROLES_PUESTO: { value: RolPuesto; label: string }[] = [
  { value: 'dignatario',               label: 'Dignatario' },
  { value: 'ejecucion_coordinacion',   label: 'Ejecución y coordinación de procesos' },
  { value: 'ejecucion_procesos',       label: 'Ejecución de procesos' },
  { value: 'ejecucion_procesos_apoyo', label: 'Ejecución de procesos de apoyo' },
  { value: 'administrativo',           label: 'Administrativo' },
  { value: 'codigo_trabajo',           label: 'Código del Trabajo' },
]

/**
 * Los roles que caben en un régimen. El de «Código del Trabajo» es el de los
 * obreros y solo va con ese régimen; ese régimen no lleva un rol de la LOSEP
 * (lo exige el backend, `CoherenciaDelPuesto`).
 */
export function rolesDelRegimen(regimen: RegimenPuesto) {
  return ROLES_PUESTO.filter((r) => (r.value === 'codigo_trabajo') === (regimen === 'codigo_trabajo'))
}

export type ComplejidadPuesto = 'bajo' | 'medio' | 'alto'

/** «Complejidad alta», no «alto»: el valor guardado concuerda con «nivel». */
export const COMPLEJIDADES: { value: ComplejidadPuesto; label: string }[] = [
  { value: 'bajo',  label: 'Baja' },
  { value: 'medio', label: 'Media' },
  { value: 'alto',  label: 'Alta' },
]

export function etiquetaComplejidad(complejidad: string): string {
  return COMPLEJIDADES.find((c) => c.value === complejidad)?.label ?? complejidad
}
