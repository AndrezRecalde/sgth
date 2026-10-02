import { z } from 'zod/v4'
import { noEsFutura } from '@/lib/fecha'

/**
 * La entrega del kit completo: todos los equipos que el puesto del servidor
 * requiere, en un solo movimiento.
 *
 * `incluido` y `nombre` no viajan al servidor —el primero decide qué filas se
 * envían, el segundo es la etiqueta de la casilla—, pero viven en el
 * formulario porque son lo que la persona marca y lee.
 *
 * Lo mismo vale para `estado`, `reponerDesde` y `ultimaEntrega`: los calcula
 * el backend al devolver el kit del servidor y la fila los muestra para
 * explicar por qué viene marcada o no. El servidor no los recibe de vuelta
 * porque los acaba de calcular él.
 */
export const entregaKitEppSchema = z.object({
  servidor_id: z
    .number({ error: 'Seleccione el servidor' })
    .min(1, 'Seleccione el servidor'),
  fecha_entrega: z
    .string()
    .min(1, 'La fecha es obligatoria')
    // El backend la rechaza con `before_or_equal:today`; sin esto el aviso
    // llegaba después del viaje al servidor.
    .refine(noEsFutura, 'La entrega no puede ser en el futuro'),
  observaciones: z.string().max(1000, 'Máximo 1000 caracteres').optional(),
  equipos: z
    .array(
      z.object({
        equipo_proteccion_id: z.number().min(1),
        nombre: z.string(),
        cantidad: z
          .number({ error: 'Indique la cantidad' })
          .int('La cantidad va en números enteros')
          .min(1, 'Mínimo 1'),
        incluido: z.boolean(),
        estado: z.enum(['pendiente', 'por_reponer', 'vigente']).optional(),
        reponerDesde: z.string().nullable().optional(),
        ultimaEntrega: z.string().nullable().optional(),
      }),
    )
    .refine(
      (equipos) => equipos.some((e) => e.incluido),
      'Marque al menos un equipo para entregar',
    ),
})

export type EntregaKitEppFormData = z.infer<typeof entregaKitEppSchema>
