/**
 * Lo que comparten las barras de filtro del módulo.
 *
 * Varios listados del backend filtran por `estado`, que en SSO es un booleano
 * —activo o retirado, investigación abierta o cerrada— y no una escala. El
 * `Select` de Mantine v9 trabaja con cadenas, así que la conversión vive aquí
 * y no repetida en cada pantalla: escrita cuatro veces, basta que una mande
 * `'false'` tal cual para que el listado filtre por un valor que el backend
 * lee como verdadero.
 */

export const ESTADO_ACTIVO_OPTIONS = [
  { value: 'true', label: 'Activos' },
  { value: 'false', label: 'Inactivos' },
]

export const ESTADO_INVESTIGACION_OPTIONS = [
  { value: 'true', label: 'Abierta' },
  { value: 'false', label: 'Cerrada' },
]

/** `undefined` cuando no hay filtro: así el parámetro no viaja en la URL. */
export function aEstadoActivo(valor: string | null): boolean | undefined {
  if (valor === null) return undefined
  return valor === 'true'
}
