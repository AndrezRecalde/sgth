import { z } from 'zod/v4'
import { TIPOS_RETROACTIVOS } from './permisos.constants'

export const permisoSchema = z.object({
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad' }),
  servidor_id: z.number({ error: 'Seleccione el servidor' })
    .min(1, 'Seleccione el servidor'),
  jefe_id: z.number({ error: 'Seleccione el jefe' }).optional().nullable(),
  tipo: z.enum(['personal', 'oficial', 'enfermedad', 'calamidad']),
  fecha: z.string().min(1, 'La fecha es requerida'),
  hora_inicio: z.string().min(1, 'Requerido'),
  hora_fin: z.string().min(1, 'Requerido'),
  observacion: z.string().optional().nullable(),
})

export type PermisoFormData = z.infer<typeof permisoSchema>

export const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)

  return new Date(y, m - 1, d)
}

export const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  if (typeof d === 'string') return d.substring(0, 10)

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export const esTipoRetroactivo = (tipo: string): boolean =>
  TIPOS_RETROACTIVOS.includes(tipo)

/**
 * La fecha más antigua admitida para un permiso planificable: tres días
 * hábiles atrás, la misma tolerancia que aplica el backend.
 *
 * Saltar solo los fines de semana deja el calendario un poco más permisivo que
 * el servidor cuando hay feriados de por medio, y es preferible a bloquear un
 * día válido: el servidor tiene la última palabra y devuelve el motivo exacto.
 */
export const minimoPlanificable = (): Date => {
  const fecha = new Date()
  let habiles = 0

  while (habiles < 3) {
    fecha.setDate(fecha.getDate() - 1)
    const dia = fecha.getDay()
    if (dia !== 0 && dia !== 6) habiles++
  }

  return fecha
}
