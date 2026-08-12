import { TIPO_LABELS, type AccionTipo } from './taxonomiaAccionPersonal'

export interface CategoriaAccionPersonal {
  value: AccionTipo
  label: string
  requiereVinculo: boolean
}

/**
 * El único tipo que no exige un vínculo previo: es el que lo crea.
 */
const CREAN_EL_VINCULO: AccionTipo[] = ['ingreso']

/**
 * Las categorías que ofrece el asistente, derivadas de la misma taxonomía que
 * usa el formulario.
 *
 * Antes era una lista escrita a mano y se había separado en cinco puntos:
 * ofrecía "Ascenso" —retirado en 2026-07-23 por no existir en la operación
 * real del GAD—, más "Traslado" y "Traspaso", que dejaron de ser tipos cuando
 * pasaron a ser subtipos de Cambio Administrativo; y omitía Cesación de
 * Funciones y Régimen Disciplinario, que sí existen y el formulario maneja.
 * Derivarla es lo que impide que vuelvan a separarse.
 */
export const CATEGORIAS_ACCION_PERSONAL: CategoriaAccionPersonal[] =
  (Object.keys(TIPO_LABELS) as AccionTipo[]).map((value) => ({
    value,
    label: TIPO_LABELS[value],
    requiereVinculo: !CREAN_EL_VINCULO.includes(value),
  }))

/**
 * true si la categoría está habilitada dado el estado de vínculo del
 * servidor seleccionado. pendiente_vinculacion null (desconocido) deshabilita
 * todo por defecto.
 */
export function categoriaHabilitada(
  categoria: CategoriaAccionPersonal,
  pendienteVinculacion: boolean | null | undefined
): boolean {
  if (pendienteVinculacion === true) return !categoria.requiereVinculo
  if (pendienteVinculacion === false) return categoria.requiereVinculo
  return false
}
