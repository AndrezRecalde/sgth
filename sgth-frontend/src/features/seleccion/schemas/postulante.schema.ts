import { z } from 'zod/v4'

/**
 * La inscripción de un candidato. En un contenedor express el puesto lo trae
 * el aspirante y es obligatorio; en un concurso formal lo fija la convocatoria
 * y no se envía. Antes el puesto y la fecha vivían en `useState`, fuera del
 * esquema (regla 07), y su error se pintaba aparte.
 */
export const esquemaInscripcion = (requierePuesto: boolean) => z.object({
  // Diez dígitos, como el backend y el Expediente (2026-10-04).
  cedula:           z.string().regex(/^\d{10}$/, 'La cédula debe tener 10 dígitos numéricos'),
  nombres:          z.string().min(2, 'Ingrese el primer nombre'),
  segundo_nombre:   z.string().optional().nullable(),
  apellidos:        z.string().min(2, 'Ingrese el primer apellido'),
  segundo_apellido: z.string().optional().nullable(),
  correo:           z.email('Correo inválido'),
  telefono:         z.string().optional().nullable(),
  /**
   * Obligatorio: al incorporar al aspirante este valor se copia a su
   * expediente de servidor, donde el género es requerido. Además la ficha
   * FEMO lo usa para decidir qué bloque reproductivo del MSP mostrar.
   */
  genero:           z.enum(['masculino', 'femenino', 'otro'], { message: 'Seleccione el género' }),
  estado_civil:     z.string().optional().nullable(),
  fecha_nacimiento: z.string().optional().nullable(),
  tipo_sangre:      z.string().optional().nullable(),
  // En el campo y no en un `superRefine`: Zod 4 solo corre el refine si el
  // resto del objeto ya es válido, y el error del puesto salía al final.
  puesto_id: requierePuesto
    ? z.number({ error: 'Seleccione el puesto al que aspira' })
    : z.number().nullable(),
  fecha_inscripcion: z.string().nullable(),
})

export type InscripcionFormData = z.infer<ReturnType<typeof esquemaInscripcion>>

/** Los campos a los que el backend puede devolver un 422. */
export const CAMPOS_INSCRIPCION = [
  'cedula', 'nombres', 'segundo_nombre', 'apellidos', 'segundo_apellido', 'correo', 'telefono',
  'genero', 'estado_civil', 'fecha_nacimiento', 'tipo_sangre', 'puesto_id', 'fecha_inscripcion',
] as const
