import { z } from 'zod/v4'

/** Un examen del catálogo, con los topes de `StoreCatalogoExamenRequest`. */
export const examenCatalogoSchema = z.object({
  area:          z.string().min(1, 'Elija el área'),
  nombre:        z.string().trim().min(1, 'Escriba el nombre').max(150, 'Máximo 150 caracteres'),
  etiqueta_nota: z.string().max(40, 'Máximo 40 caracteres'),
  indicaciones:  z.string().max(1000, 'Máximo 1000 caracteres'),
  sensible:      z.boolean(),
  activo:        z.boolean(),
})

export type ExamenCatalogoFormData = z.infer<typeof examenCatalogoSchema>

/**
 * Un perfil, con los topes de `StorePerfilExamenRequest`. Que uno ocupacional
 * no lleve sensibles lo mira el modal, que tiene el catálogo, y el servidor.
 */
export const perfilExamenSchema = z.object({
  especialidad: z.enum(['medicina_general', 'odontologia']),
  nombre:       z.string().trim().min(1, 'Escriba el nombre').max(100, 'Máximo 100 caracteres'),
  descripcion:  z.string().max(300, 'Máximo 300 caracteres'),
  ocupacional:  z.boolean(),
  activo:       z.boolean(),
  examenes:     z.array(z.number()).min(1, 'Marque al menos un examen').max(100, 'Máximo 100 exámenes'),
})

export type PerfilExamenFormData = z.infer<typeof perfilExamenSchema>
