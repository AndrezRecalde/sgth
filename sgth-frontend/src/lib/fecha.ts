/**
 * Conversión de fechas entre el backend y los selectores de Mantine.
 *
 * Vive en `lib/` porque la usan varios módulos. Antes había ocho copias de
 * estas mismas tres funciones: una en `features/sso/utils`, otra en
 * `features/disciplinario/utils` y seis pegadas dentro de componentes de la
 * ficha FEMO, todas ligeramente distintas entre sí.
 *
 * El backend maneja fechas como `YYYY-MM-DD`. Se construyen con el constructor
 * de tres argumentos y no con `new Date(cadena)`, que interpreta esa forma como
 * UTC y en Ecuador (UTC-5) devuelve el día anterior.
 */

/** Fecha del backend a `DD/MM/AAAA`, para mostrar. Sin dato, un guion. */
export function formatFecha(value?: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** `YYYY-MM-DD` (o ISO completo) del backend a `Date` local, para el selector. */
export function toDateValue(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.substring(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

/**
 * `Date` del selector a `YYYY-MM-DD`. Sin fecha devuelve cadena vacía.
 * Para un campo obligatorio, o cuando el valor va directo a un texto.
 */
export function fromDateValue(d: Date | string | null): string {
  if (!d) return ''
  if (typeof d === 'string') return d.substring(0, 10)
  if (isNaN(d.getTime())) return ''
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

/**
 * Igual que `fromDateValue`, pero devuelve `null` cuando no hay fecha.
 *
 * Para campos opcionales que se limpian: el backend distingue "sin fecha"
 * (`null`) de una cadena vacía, que le llega como fecha inválida.
 */
export function fromDateValueOrNull(d: Date | string | null): string | null {
  return fromDateValue(d) || null
}

/**
 * Igual, pero `undefined` cuando no hay fecha.
 *
 * Para los filtros que viajan como parámetro de consulta: axios omite del
 * `params` las claves `undefined`, mientras que `null` llega al backend como
 * un valor vacío y pasa por el validador. «Sin filtro» y «filtro vacío» no
 * son lo mismo.
 */
export function fromDateValueOrUndefined(
  d: Date | string | null,
): string | undefined {
  return fromDateValue(d) || undefined
}

/**
 * Hoy en `YYYY-MM-DD`, hora local.
 *
 * Es `fromDateValue(new Date())`, pero se repetía escrito a mano para poner la
 * fecha por defecto de un formulario o comparar contra el día de hoy.
 */
export function hoyIso(): string {
  return fromDateValue(new Date())
}

/**
 * ¿La fecha `YYYY-MM-DD` es de hoy o anterior?
 *
 * El espejo del `before_or_equal:today` de Laravel, para que el aviso salga en
 * el campo y no después del viaje al servidor. Compara cadenas, que en este
 * formato ordenan igual que las fechas, y contra `hoyIso()` —la hora local—:
 * con `new Date().toISOString()` el día cambia a las 19:00 en Ecuador.
 *
 * Sin fecha devuelve `true`: que el campo esté vacío lo dice el `min(1)`, no
 * esta comprobación, y dos mensajes para un mismo hueco sobran.
 */
export function noEsFutura(fecha?: string | null): boolean {
  if (!fecha) return true
  return fecha.substring(0, 10) <= hoyIso()
}

/**
 * `Date` de un selector de fecha y hora a `YYYY-MM-DDTHH:mm`, en la hora
 * local, que es como lo espera el backend. Sin fecha, cadena vacía.
 *
 * Viáticos tenía tres copias de esta función, una por formulario.
 */
export function fromDateTimeValue(d: Date | string | null): string {
  if (!d) return ''
  const dt = typeof d === 'string' ? new Date(d) : d
  if (isNaN(dt.getTime())) return ''
  const dos = (n: number) => String(n).padStart(2, '0')
  return `${dt.getFullYear()}-${dos(dt.getMonth() + 1)}-${dos(dt.getDate())}T${dos(dt.getHours())}:${dos(dt.getMinutes())}`
}

/**
 * Fecha y hora del backend a `DD/MM/AAAA HH:MM`, en la hora local.
 *
 * Para columnas `datetime`: el backend las serializa en ISO con zona
 * (`2026-10-20T13:00:00.000000Z`) y `new Date` las lleva a la hora de
 * Ecuador. Viáticos las pasaba antes por `replace(/-/g, '/')`, un truco para
 * el formato `2026-10-20 08:00:00` que con ISO da una fecha inválida: el
 * listado mostraba «Invalid Date» en el período de todos los viáticos.
 */
export function formatFechaHora(
  value?: string | null,
  { conHora = true }: { conHora?: boolean } = {},
): string {
  if (!value) return '—'
  const dt = new Date(value)
  if (isNaN(dt.getTime())) return '—'
  return dt.toLocaleString('es-EC', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...(conHora ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

/**
 * Fecha del backend a `DD mmm AAAA` (`31 ago 2026`). Sin dato, un guion.
 *
 * El sistema usa dos formatos de fecha a la vista, y los dos son deliberados:
 * `formatFecha` —numérico— para tablas densas y formularios, donde lo que se
 * hace es comparar y alinear en columna; este —con el mes en letras— para
 * tarjetas, cajones de detalle y encabezados, donde se lee una sola fecha y
 * `05/09` frente a `09/05` es una ambigüedad que no hace falta correr.
 *
 * Estaba escrito a mano en 19 sitios, ninguno de ellos con `timeZone`. Aquí
 * lo lleva, igual que `formatFecha`: los campos que llegan son `date`, que
 * Laravel serializa como la medianoche local convertida a UTC
 * (`2026-08-31T05:00:00.000000Z`), y leerlos en UTC da el mismo día en
 * cualquier parte. Sin `timeZone` el día depende del reloj de quien mira: en
 * Ecuador sale bien, pero un navegador al oeste de UTC-5 enseñaba el anterior.
 */
export function formatFechaMes(value?: string | null): string {
  if (!value) return '—'
  const dt = new Date(value)
  if (isNaN(dt.getTime())) return '—'
  return dt.toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

/**
 * Fecha y hora a `DD mmm AAAA HH:mm` (`31 ago 2026, 08:00 a. m.`).
 *
 * El pariente de `formatFechaMes` para lo que sí lleva hora: un movimiento de
 * kardex, la corrección de una consulta. Aquí **no** va `timeZone`, y es a
 * propósito: lo que se pinta es un instante real, y un instante se lee en la
 * hora de quien lo mira, no en UTC.
 */
export function formatFechaMesHora(value?: string | null): string {
  if (!value) return '—'
  const dt = new Date(value)
  if (isNaN(dt.getTime())) return '—'
  return dt.toLocaleString('es-EC', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
