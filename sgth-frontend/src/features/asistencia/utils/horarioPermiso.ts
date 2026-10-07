/*
| Cómo se lee la fecha, el horario y el plazo de un permiso.
|
| Lo usan las dos tablas de permisos —la de Talento Humano y «Mis permisos»
| del portal— y el formulario, que muestra la duración mientras se eligen las
| horas. La tabla de Talento Humano tenía su propia copia de `duracion` y del
| cálculo del vencimiento.
*/

/** Minutos entre «08:00» y «12:30»; negativos si el fin es anterior al inicio. */
export function minutosEntre(horaInicio: string, horaFin: string): number {
  const [hI, mI] = horaInicio.substring(0, 5).split(':').map(Number)
  const [hF, mF] = horaFin.substring(0, 5).split(':').map(Number)

  return hF * 60 + mF - (hI * 60 + mI)
}

/** «08:00» y «12:30» → «4h 30m». */
export function duracion(horaInicio: string, horaFin: string): string {
  const minutos = minutosEntre(horaInicio, horaFin)
  const horas = Math.floor(minutos / 60)
  const mins = minutos % 60

  return horas > 0 ? `${horas}h${mins > 0 ? ` ${mins}m` : ''}` : `${mins}m`
}

/** Días que faltan para que venza el plazo del respaldo; 0 o menos si ya venció. */
export function diasParaVencer(venceEn: string): number {
  return Math.ceil((new Date(venceEn).getTime() - Date.now()) / 86_400_000)
}
