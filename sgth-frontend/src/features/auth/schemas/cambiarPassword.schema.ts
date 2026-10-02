import { z } from 'zod/v4'

interface RequisitoContrasena {
  texto: string
  /** Sin comprobación cuando el navegador no tiene el dato (la cédula). */
  cumple?: (valor: string) => boolean
}

/**
 * Las mismas reglas que `CambiarContrasenaRequest` en el backend. Las usa el
 * esquema para validar y `RequisitosContrasena` para enseñarlas.
 */
export const REQUISITOS_CONTRASENA: RequisitoContrasena[] = [
  { texto: 'Al menos 8 caracteres', cumple: (v) => v.length >= 8 },
  { texto: 'Al menos una letra',    cumple: (v) => /[a-zA-Z]/.test(v) },
  { texto: 'Al menos un número',    cumple: (v) => /[0-9]/.test(v) },
  { texto: 'Que no contenga su número de cédula' },
]

export const cambiarPasswordSchema = z.object({
  nueva_contrasena: z
    .string()
    .min(8, 'Mínimo 8 caracteres')
    .regex(/[a-zA-Z]/, 'Debe contener al menos una letra')
    .regex(/[0-9]/, 'Debe contener al menos un número'),
  confirmar_contrasena: z
    .string()
    .min(1, 'Repita la nueva contraseña'),
}).refine(
  (data) => data.nueva_contrasena === data.confirmar_contrasena,
  {
    message: 'Las contraseñas no coinciden',
    path: ['confirmar_contrasena'],
  }
)

export type CambiarPasswordFormData = z.infer<typeof cambiarPasswordSchema>

// Tipo que el backend realmente espera
export type CambiarPasswordPayload = {
  nueva_contrasena: string
}
