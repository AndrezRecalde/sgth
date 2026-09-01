import { z } from 'zod/v4'

/**
 * `servidor_id` no se valida a propósito.
 *
 * Al crear lo garantiza la máquina de pasos del drawer: no se llega a
 * "configurar" sin haber elegido un servidor. Al editar simplemente no existe
 * —hay usuarios sin ficha, los que se desvincularon— y el formulario ni
 * siquiera muestra el campo. Cuando el esquema lo exigía en ambos casos,
 * editar a uno de esos usuarios fallaba contra un campo invisible: el botón
 * Guardar no hacía absolutamente nada y no aparecía ningún error.
 */
export const usuarioSchema = z.object({
  email: z.string().email('Email inválido'),
  usuario_ti: z
    .string()
    .min(3, 'Mínimo 3 caracteres')
    .regex(/^[a-z0-9]+$/, 'Solo letras minúsculas y números'),
  roles: z.array(z.string()).min(1, 'Asigne al menos un rol'),
  servidor_id: z.number().nullish(),
})

export type UsuarioFormValues = z.infer<typeof usuarioSchema>
