/**
 * Reglas de la jerarquía del orgánico, compartidas por el formulario, el
 * árbol, el organigrama gráfico y la página pública.
 *
 * El espejo de `EstructuraService::PROFUNDIDAD_MAXIMA` en el backend, que es
 * quien manda: aquí solo sirve para no ofrecer en pantalla lo que la API va a
 * rechazar.
 */
export const PROFUNDIDAD_MAXIMA = 3

/** Cómo se llama cada nivel en la institución. */
export const NIVELES = [
  'Institución',
  'Unidad administrativa',
  'Subproceso',
] as const

function indiceNivel(nivel: number): number {
  return Math.min(Math.max(nivel, 1), NIVELES.length) - 1
}

export function etiquetaNivel(nivel: number): string {
  return NIVELES[indiceNivel(nivel)]
}

/**
 * «Nueva institución», «Nueva unidad administrativa», «Nuevo subproceso».
 *
 * El artículo va junto al nombre del nivel porque los tres no concuerdan en
 * género, y armarlo en cada pantalla acababa en un «Nuevo unidad».
 */
const ARTICULOS = ['Nueva', 'Nueva', 'Nuevo'] as const

export function tituloNuevo(nivel: number): string {
  const i = indiceNivel(nivel)
  return `${ARTICULOS[i]} ${NIVELES[i].toLowerCase()}`
}

/** Un nivel puede tener unidades debajo mientras no sea el último. */
export function admiteSubunidades(nivel: number | undefined): boolean {
  return (nivel ?? 1) < PROFUNDIDAD_MAXIMA
}
