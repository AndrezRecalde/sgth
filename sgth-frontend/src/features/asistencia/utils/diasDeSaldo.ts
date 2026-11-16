import { plural } from '@/lib/plural'

/**
 * «29.75 días», «30 días», «1 día»: con decimales solo si los tiene —los
 * permisos por horas dejan el saldo fraccionado—, hasta dos, y con punto como
 * el resto del sistema.
 */
export function diasDeSaldo(saldo: number): string {
  return plural(Number(saldo.toFixed(2)), 'día', 'días')
}
