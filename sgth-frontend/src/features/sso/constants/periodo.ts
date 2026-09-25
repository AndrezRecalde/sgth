/**
 * El período con el que trabaja todo el módulo SSO: un año ('2026') o un mes
 * ('2026-07').
 *
 * La misma expresión estaba escrita diez veces —dos por cada pantalla que pide
 * un período, más los dos esquemas de campaña—, y el backend la tenía en siete
 * controladores. Ahora es una sola a cada lado, y las dos dicen lo mismo:
 * `PeriodoSso` en el backend acepta exactamente esto.
 *
 * El mes va de 01 a 12. La versión anterior aceptaba `2026-13`, y en el
 * servidor `Carbon` lo desbordaba en silencio a enero de 2027.
 */
export const PATRON_PERIODO = /^\d{4}(-(0[1-9]|1[0-2]))?$/

/** La ayuda que va bajo el campo, igual en las cuatro pantallas. */
export const AYUDA_PERIODO = 'Formato AAAA (año) o AAAA-MM (mes)'

/** El marcador de posición del campo. */
export const EJEMPLO_PERIODO = '2026 o 2026-07'

export function esPeriodoValido(valor: string): boolean {
  return PATRON_PERIODO.test(valor)
}
