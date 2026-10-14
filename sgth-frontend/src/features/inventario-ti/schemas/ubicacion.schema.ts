import { z } from 'zod/v4'

/** Sin ubicación de origen: los equipos de la unidad que no tienen ninguna. */
export const SIN_UBICACION = 'sin'

/**
 * La mudanza de una unidad. Que el destino no sea el origen y que haya
 * equipos que mover lo comprueba el backend.
 */
export const mudanzaSchema = z.object({
  unidad_administrativa_id: z.number({ message: 'Elija la unidad' }),
  desde: z.string({ message: 'Elija de dónde sale' }).min(1, 'Elija de dónde sale'),
  hacia_ubicacion_id: z.number({ message: 'Elija a dónde va' }),
})
export type MudanzaFormData = z.infer<typeof mudanzaSchema>
