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

/** La contraseña nueva, igual en el primer acceso y en el cambio voluntario. */
export const nuevaContrasenaSchema = z
  .string()
  .min(8, 'Mínimo 8 caracteres')
  .regex(/[a-zA-Z]/, 'Debe contener al menos una letra')
  .regex(/[0-9]/, 'Debe contener al menos un número')

export const cambiarPasswordSchema = z.object({
  nueva_contrasena: nuevaContrasenaSchema,
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

/** Cambio voluntario: además pide la actual, que el backend comprueba. */
export const actualizarContrasenaSchema = z.object({
  contrasena_actual:    z.string().min(1, 'Ingrese su contraseña actual'),
  nueva_contrasena:     nuevaContrasenaSchema,
  confirmar_contrasena: z.string().min(1, 'Repita la nueva contraseña'),
}).refine(
  (data) => data.nueva_contrasena === data.confirmar_contrasena,
  { message: 'Las contraseñas no coinciden', path: ['confirmar_contrasena'] },
).refine(
  (data) => data.nueva_contrasena !== data.contrasena_actual,
  { message: 'La nueva contraseña debe ser distinta de la actual', path: ['nueva_contrasena'] },
)

export type ActualizarContrasenaFormData = z.infer<typeof actualizarContrasenaSchema>

export type ActualizarContrasenaPayload = Pick<
  ActualizarContrasenaFormData,
  'contrasena_actual' | 'nueva_contrasena'
>
