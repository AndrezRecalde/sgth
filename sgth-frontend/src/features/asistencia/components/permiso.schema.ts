import { z } from 'zod/v4'
import { TIPOS_RETROACTIVOS } from './permisos.constants'

export const permisoSchema = z.object({
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad' }),
  servidor_id: z.number({ error: 'Seleccione el servidor' })
    .min(1, 'Seleccione el servidor'),
  jefe_id: z.number({ error: 'Seleccione el jefe' }).optional().nullable(),
  // En true, el jefe lo resuelve el backend y `jefe_id` no se envía.
  dirigido_a_talento_humano: z.boolean(),
  tipo: z.enum(['personal', 'oficial', 'enfermedad', 'calamidad']),
  fecha: z.string().min(1, 'La fecha es requerida'),
  hora_inicio: z.string().min(1, 'Requerido'),
  hora_fin: z.string().min(1, 'Requerido'),
  observacion: z.string().optional().nullable(),
})

export type PermisoFormData = z.infer<typeof permisoSchema>

export const esTipoRetroactivo = (tipo: string): boolean =>
  TIPOS_RETROACTIVOS.includes(tipo)

/**
 * La fecha más antigua que se puede registrar, para cualquier tipo: dos días
 * hábiles atrás.
 *
 * El respaldo tiene 72 horas laborables desde la fecha del permiso para llegar
 * a Recepción. Un permiso más viejo tendría ese plazo ya vencido y nacería
 * como falta injustificada, así que el backend no lo admite. Antes enfermedad
 * y calamidad no tenían mínimo, y personal y oficial admitían tres días
 * hábiles atrás, cuyo plazo había vencido a las 00:00 de ese mismo día.
 *
 * Aquí solo se saltan fines de semana. Con un feriado de por medio el backend
 * admite un día más atrás que este calendario; es un caso raro, y el servidor
 * tiene la última palabra.
 */
export const fechaMasAntiguaAdmitida = (): Date => {
  const fecha = new Date()
  let habiles = 0

  while (habiles < 2) {
    fecha.setDate(fecha.getDate() - 1)
    const dia = fecha.getDay()
    if (dia !== 0 && dia !== 6) habiles++
  }

  return fecha
}
