import type { CalculoViatico } from '@/types/api'

/**
 * La cuenta con un total de comprobantes que todavía no se ha guardado.
 *
 * La fórmula vive en el backend (`CalculoViaticoService`) y llega resuelta con
 * el viático: esto solo la proyecta mientras el servidor escribe en el
 * formulario, para que vea al momento cuánto le falta por justificar. En
 * cuanto guarda, el número que manda es el del backend.
 */
export function proyectarCalculo(
  base: CalculoViatico,
  totalComprobantes: number,
): CalculoViatico {
  const redondear = (valor: number) => Math.round(valor * 100) / 100

  const justificado = redondear(Math.min(totalComprobantes, base.tope_justificable))
  const reconocido = redondear(justificado + base.reconocido_sin_comprobante)

  return {
    ...base,
    total_comprobantes: redondear(totalComprobantes),
    justificado,
    excedente: redondear(Math.max(totalComprobantes - base.tope_justificable, 0)),
    reconocido,
    saldo: redondear(reconocido - base.anticipo),
  }
}
