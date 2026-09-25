import { z } from 'zod/v4'

/**
 * El EPP requerido de un puesto: de aquí sale el kit que se entrega al
 * servidor.
 *
 * `cantidad_requerida` y la frecuencia son opcionales en el backend, pero el
 * formulario arranca la cantidad en 1: un requerimiento de cero equipos no
 * significa nada.
 */
export const puestoEppSchema = z.object({
  equipo_proteccion_id: z
    .number({ error: 'Seleccione el equipo' })
    .min(1, 'Seleccione el equipo'),
  cantidad_requerida: z
    .number({ error: 'Indique la cantidad' })
    .int('La cantidad va en números enteros')
    .min(1, 'Mínimo 1'),
  frecuencia_reposicion_meses: z
    .number()
    .int('Los meses van en números enteros')
    .min(1, 'Mínimo 1 mes')
    .nullable(),
})

export type PuestoEppFormData = z.infer<typeof puestoEppSchema>
