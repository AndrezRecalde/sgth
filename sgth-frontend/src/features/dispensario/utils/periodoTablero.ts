/**
 * Los meses que ofrece el selector de período de los tableros del Dispensario.
 *
 * Un mes es `AAAA-MM`. Se calcula en hora local y sin `new Date('AAAA-MM-DD')`,
 * que en Ecuador cae en el día anterior.
 */

export interface PeriodoTablero {
  desde: string
  hasta: string
}

const dosDigitos = (n: number) => String(n).padStart(2, '0')

/** El mes en curso, en `AAAA-MM`. */
export function mesActual(): string {
  const hoy = new Date()
  return `${hoy.getFullYear()}-${dosDigitos(hoy.getMonth() + 1)}`
}

/** «septiembre de 2026». */
export function etiquetaMes(mes: string): string {
  const [anio, m] = mes.split('-').map(Number)
  return new Date(anio, m - 1, 1).toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })
}

/** Los últimos `n` meses, del más reciente al más antiguo, para un Select. */
export function mesesRecientes(n = 12): { value: string; label: string }[] {
  const hoy = new Date()
  return Array.from({ length: n }, (_, i) => {
    const fecha = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1)
    const valor = `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}`
    return { value: valor, label: etiquetaMes(valor) }
  })
}

/** Primer y último día del mes. */
export function rangoDeMes(mes: string): PeriodoTablero {
  const [anio, m] = mes.split('-').map(Number)
  const ultimo = new Date(anio, m, 0).getDate()
  return { desde: `${mes}-01`, hasta: `${mes}-${dosDigitos(ultimo)}` }
}
