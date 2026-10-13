import { z } from 'zod/v4'

/**
 * La solicitud de préstamo. El horario contra las reglas de TIC (atención,
 * anticipación, plazo) y si los equipos están libres los comprueba el backend,
 * y su respuesta cae en el campo.
 */
export const solicitudSchema = z.object({
  bienes: z.array(z.number()).min(1, 'Elija al menos un equipo').max(10, 'Hasta 10 equipos por solicitud'),
  inicio_previsto: z.string().min(1, 'Indique cuándo lo retira'),
  fin_previsto: z.string().min(1, 'Indique cuándo lo devuelve'),
  motivo: z.string().trim().min(5, 'Diga para qué lo necesita (mínimo 5 caracteres)').max(1000, 'Máximo 1000 caracteres'),
  lugar_uso: z.string().trim().min(3, 'Diga dónde lo va a usar').max(200, 'Máximo 200 caracteres'),
  fuera_del_edificio: z.boolean(),
}).refine((v) => !v.inicio_previsto || !v.fin_previsto || v.fin_previsto > v.inicio_previsto, {
  path: ['fin_previsto'],
  message: 'La devolución tiene que ser después de la entrega',
})

export type SolicitudFormData = z.infer<typeof solicitudSchema>
