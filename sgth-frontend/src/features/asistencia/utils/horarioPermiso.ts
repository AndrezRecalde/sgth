/*
| Cómo se lee la fecha, el horario y el plazo de un permiso.
|
| Lo usa la pantalla «Mis permisos» del Portal del Servidor. El listado de
| Talento Humano (`permisos.columns.tsx`) tiene su propia copia de `duracion`;
| unificarla ahí queda para cuando se fusionen los PR que tocan ese archivo.
*/

/** «08:00» y «12:30» → «4h 30m». */
export function duracion(horaInicio: string, horaFin: string): string {
  const [hI, mI] = horaInicio.substring(0, 5).split(':').map(Number)
  const [hF, mF] = horaFin.substring(0, 5).split(':').map(Number)
  const minutos = hF * 60 + mF - (hI * 60 + mI)
  const horas = Math.floor(minutos / 60)
  const mins = minutos % 60

  return horas > 0 ? `${horas}h${mins > 0 ? ` ${mins}m` : ''}` : `${mins}m`
}

/**
 * La fecha del permiso en día/mes/año.
 *
 * Llega como medianoche UTC: sin `timeZone: 'UTC'`, en Ecuador (UTC−5) se
 * mostraría el día anterior.
 */
export function fechaPermiso(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

/** Días que faltan para que venza el plazo del respaldo; 0 o menos si ya venció. */
export function diasParaVencer(venceEn: string): number {
  return Math.ceil((new Date(venceEn).getTime() - Date.now()) / 86_400_000)
}
