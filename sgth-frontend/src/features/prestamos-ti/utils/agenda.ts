import type { BloqueOcupado } from '../services/tipos'

/**
 * La aritmética de la agenda: qué días muestra, y dónde cae cada bloque
 * ocupado dentro del horario de atención de un día. Todo en la hora local:
 * el backend manda las fechas en ISO con zona y `new Date` las trae a Ecuador.
 */

const dos = (n: number) => String(n).padStart(2, '0')

/** `Date` a `YYYY-MM-DD` en la hora local. */
export function isoDia(d: Date): string {
  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
}

/** `YYYY-MM-DD` a la medianoche local de ese día. */
export function aFecha(dia: string): Date {
  const [a, m, d] = dia.split('-').map(Number)
  return new Date(a, m - 1, d)
}

export function sumarDiasIso(dia: string, n: number): string {
  const d = aFecha(dia)
  d.setDate(d.getDate() + n)
  return isoDia(d)
}

/** Los días de la semana del día dado, de lunes a viernes o a domingo. */
export function semanaDe(dia: string, conFinDeSemana: boolean): string[] {
  const d = aFecha(dia)
  const lunes = sumarDiasIso(dia, -((d.getDay() + 6) % 7))
  return Array.from({ length: conFinDeSemana ? 7 : 5 }, (_, i) => sumarDiasIso(lunes, i))
}

export function esFinDeSemana(dia: string): boolean {
  const n = aFecha(dia).getDay()
  return n === 0 || n === 6
}

/** El instante `dia` a la hora `HH:mm`. */
function momento(dia: string, hhmm: string): Date {
  const [h, m] = hhmm.split(':').map(Number)
  const d = aFecha(dia)
  d.setHours(h, m, 0, 0)
  return d
}

/**
 * Dónde pintar un bloque dentro de la barra de un día, en porcentaje del
 * horario de atención. Null si ese día no lo toca.
 */
export function posicionEnDia(bloque: BloqueOcupado, dia: string, apertura: string, cierre: string): { izquierda: number; ancho: number } | null {
  const desde = momento(dia, apertura).getTime()
  const hasta = momento(dia, cierre).getTime()
  const inicio = Math.max(new Date(bloque.inicio).getTime(), desde)
  const fin = Math.min(new Date(bloque.fin).getTime(), hasta)
  if (fin <= inicio) return null

  const total = hasta - desde
  return { izquierda: ((inicio - desde) / total) * 100, ancho: ((fin - inicio) / total) * 100 }
}

/**
 * La hora que corresponde a un punto de la barra (0 a 1), redondeada hacia
 * abajo a la media hora, como `YYYY-MM-DDTHH:mm`.
 */
export function horaEnBarra(dia: string, fraccion: number, apertura: string, cierre: string): string {
  const desde = momento(dia, apertura).getTime()
  const hasta = momento(dia, cierre).getTime()
  const media = 30 * 60_000
  const t = new Date(Math.floor((desde + (hasta - desde) * Math.min(Math.max(fraccion, 0), 1)) / media) * media)
  return `${isoDia(t)}T${dos(t.getHours())}:${dos(t.getMinutes())}`
}

/** Las horas enteras del horario de atención, para las marcas de la barra. */
export function horasDeAtencion(apertura: string, cierre: string): number[] {
  const primera = Math.ceil(Number(apertura.split(':')[0]) + Number(apertura.split(':')[1]) / 60)
  const ultima = Math.floor(Number(cierre.split(':')[0]) + Number(cierre.split(':')[1]) / 60)
  return Array.from({ length: Math.max(ultima - primera + 1, 0) }, (_, i) => primera + i)
}

/** Fracción del día de atención en que cae una hora entera. */
export function fraccionDeHora(hora: number, apertura: string, cierre: string): number {
  const aMin = (hhmm: string) => Number(hhmm.split(':')[0]) * 60 + Number(hhmm.split(':')[1])
  return (hora * 60 - aMin(apertura)) / (aMin(cierre) - aMin(apertura))
}

/** `HH:mm` de una fecha ISO, en la hora local. */
export function hora(iso: string): string {
  const d = new Date(iso)
  return `${dos(d.getHours())}:${dos(d.getMinutes())}`
}

/** «hoy 15:00», «mañana 09:00» o «16/10 09:00». */
export function cuandoCorto(iso: string): string {
  const dia = isoDia(new Date(iso))
  const hoy = isoDia(new Date())
  if (dia === hoy) return `hoy ${hora(iso)}`
  if (dia === sumarDiasIso(hoy, 1)) return `mañana ${hora(iso)}`
  return `${dia.slice(8, 10)}/${dia.slice(5, 7)} ${hora(iso)}`
}

/** `DD/MM/AAAA` de una fecha ISO, en la hora local. */
export function diaCorto(iso: string): string {
  const d = isoDia(new Date(iso))
  return `${d.slice(8, 10)}/${d.slice(5, 7)}/${d.slice(0, 4)}`
}

/** «14/10/2026 · 14:00 a 15:00», o con las dos fechas si cambia de día. */
export function rangoHorario(inicio: string, fin: string): string {
  return diaCorto(inicio) === diaCorto(fin)
    ? `${diaCorto(inicio)} · ${hora(inicio)} a ${hora(fin)}`
    : `${diaCorto(inicio)} ${hora(inicio)} al ${diaCorto(fin)} ${hora(fin)}`
}

/** «14/10/2026 14:00». */
export function momentoCorto(iso: string | null): string {
  return iso ? `${diaCorto(iso)} ${hora(iso)}` : '—'
}
