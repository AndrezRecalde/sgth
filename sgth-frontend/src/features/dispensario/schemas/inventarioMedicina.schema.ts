import { z } from 'zod/v4'

export const PRESENTACION_OPTIONS = [
  { value: 'tableta',     label: 'Tableta' },
  { value: 'capsula',     label: 'Cápsula' },
  { value: 'jarabe',      label: 'Jarabe / Suspensión' },
  { value: 'gotas',       label: 'Gotas' },
  { value: 'inyectable',  label: 'Inyectable / Ampolla' },
  { value: 'crema',       label: 'Crema / Pomada' },
  { value: 'supositorio', label: 'Supositorio' },
  { value: 'spray',       label: 'Spray / Aerosol' },
  { value: 'parche',      label: 'Parche' },
  { value: 'solucion',    label: 'Solución oftálmica/ótica' },
  { value: 'polvo',       label: 'Polvo para reconstituir' },
  { value: 'otro',        label: 'Otro' },
]

/**
 * Las reglas son las de `StoreInventarioMedicinaRequest`. Los topes de
 * longitud y el entero faltaban, así que lo que el servidor rechazaba volvía
 * como un 422 suelto en vez de señalar el campo.
 */
export const medicinaSchema = z.object({
  nombre:           z.string().min(2, 'Mínimo 2 caracteres')
    .max(255, 'Máximo 255 caracteres'),
  principio_activo: z.string().min(2, 'Mínimo 2 caracteres')
    .max(255, 'Máximo 255 caracteres'),
  presentacion:     z.string().min(1, 'Seleccione la presentación'),
  concentracion:    z.string().max(100, 'Máximo 100 caracteres')
    .optional().nullable(),
  stock_minimo:     z.number()
    .int('Las unidades no se parten por la mitad')
    .min(0, 'No puede ser negativo'),
})

export type MedicinaFormData = z.infer<typeof medicinaSchema>
