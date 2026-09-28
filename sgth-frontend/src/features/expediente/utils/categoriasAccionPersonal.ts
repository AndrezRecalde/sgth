import { TIPO_LABELS, tiposElegibles, type AccionTipo } from './taxonomiaAccionPersonal'

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
 * ¿Se puede registrar esta categoría a este servidor?
 *
 * Dos condiciones. La primera es el estado del vínculo: sin vínculo solo cabe el
 * ingreso, y con vínculo cabe todo menos el ingreso. `pendiente_vinculacion` en
 * null —desconocido— deshabilita todo, porque no se puede decidir.
 *
 * La segunda es el nombramiento vigente, y faltaba. La rejilla ofrecía las ocho
 * categorías a cualquiera con vínculo, así que a un permanente se le ofrecían
 * «Cambio de Denominación» —solo obreros— y «Prestación de Servicios» —que no
 * incluye permanentes—: se elegían, se llenaba el formulario entero y el backend
 * lo rechazaba al guardar con «no aplica para el tipo de nombramiento vigente».
 * Justo el botón que va a fallar que el resto del módulo evita ofrecer.
 */
export function categoriaHabilitada(
  categoria: CategoriaAccionPersonal,
  pendienteVinculacion: boolean | null | undefined,
  tipoNombramiento?: string | null,
): boolean {
  if (pendienteVinculacion === true) return !categoria.requiereVinculo
  if (pendienteVinculacion !== false) return false

  // Tiene vínculo: el ingreso no aplica, y el resto solo si el nombramiento lo
  // admite. `tiposElegibles()` es el espejo de las reglas del backend.
  if (!categoria.requiereVinculo) return false

  return tiposElegibles(tipoNombramiento).includes(categoria.value)
}
