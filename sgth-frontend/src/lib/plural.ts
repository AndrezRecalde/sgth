/**
 * «1 día hábil», «3 días hábiles». Sustituye los «día(s) hábil(es)» que se
 * leían a medias en préstamos y tickets.
 */
export function plural(n: number, uno: string, varios: string): string {
  return `${n} ${n === 1 ? uno : varios}`
}
