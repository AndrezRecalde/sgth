import type { CategoriaActividad } from '../services/misActividadesService'

/** Las categorías de una actividad laboral (`CategoriaActividadEnum`). */
export const CATEGORIAS_ACTIVIDAD: { value: CategoriaActividad; label: string }[] = [
  { value: 'reunion', label: 'Reunión' },
  { value: 'visita_campo', label: 'Visita de campo' },
  { value: 'elaboracion_documentos', label: 'Elaboración de documentos' },
  { value: 'coordinacion', label: 'Coordinación' },
  { value: 'capacitacion', label: 'Capacitación' },
  { value: 'atencion_ciudadana', label: 'Atención ciudadana' },
  { value: 'otro', label: 'Otra' },
]
