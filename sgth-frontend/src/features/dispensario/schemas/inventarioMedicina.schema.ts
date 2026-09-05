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

export const medicinaSchema = z.object({
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  principio_activo: z.string().min(2, 'Mínimo 2 caracteres'),
  presentacion:     z.string().min(1, 'Seleccione la presentación'),
  concentracion:    z.string().optional().nullable(),
  stock_minimo:     z.number().min(0, 'No puede ser negativo'),
})

export type MedicinaFormData = z.infer<typeof medicinaSchema>
