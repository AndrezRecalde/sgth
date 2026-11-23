import type { ViaticoConRelaciones } from '@/types/api'

/** Un viático cancelado o rechazado ya no se le paga a nadie. */
const SIN_PAGO = ['cancelado', 'rechazado']

/**
 * Si hay que avisar de que al servidor le falta la cuenta bancaria.
 *
 * El backend resuelve si falta (`CuentaDeViatico::falta`); aquí solo se decide
 * cuándo vale la pena decirlo. Se avisa en toda la vida del viático menos al
 * final de los que no terminan en pago: hace falta con anticipo y sin él,
 * porque un viático sin anticipo termina igual con un saldo a favor del
 * servidor que hay que transferirle.
 */
export function faltaLaCuenta(
  viatico: Pick<ViaticoConRelaciones, 'estado' | 'sin_cuenta_bancaria'>,
): boolean {
  return viatico.sin_cuenta_bancaria === true
    && !SIN_PAGO.includes(String(viatico.estado ?? ''))
}
