import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { notificar } from '@/components/ui'
import { erroresDeCampo } from './erroresDeCampo'

/**
 * Lleva un 422 al formulario: cada error bajo su campo y, lo que no tenga
 * campo en este formulario, a una notificación con `titulo`. Si no es un 422
 * con errores por campo, no hace nada: lo notifica el hook con
 * `notificar.alFallarSalvoCampos`, que a su vez se calla cuando hay campos
 * para que el mensaje no salga dos veces.
 *
 *   mutateAsync(datos).then(cerrar).catch((e) =>
 *     erroresAlFormulario(e, setError, Object.keys(esquema.shape), 'No se pudo guardar'))
 *
 * Hasta el 2026-10-03 los modales del Expediente hacían `.catch(() => {})` y
 * el error del backend solo salía como notificación: había que buscar cuál de
 * los campos estaba mal.
 */
export function erroresAlFormulario<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  campos: readonly string[],
  titulo: string,
): void {
  const errores = erroresDeCampo(error)
  if (!errores) return

  const esCampo = (campo: string): campo is Path<T> => campos.includes(campo)
  const sueltos: string[] = []
  for (const [campo, mensaje] of Object.entries(errores)) {
    if (esCampo(campo)) setError(campo, { type: 'server', message: mensaje })
    else sueltos.push(mensaje)
  }
  if (sueltos.length > 0) notificar.error(titulo, sueltos.join(' '))
}
