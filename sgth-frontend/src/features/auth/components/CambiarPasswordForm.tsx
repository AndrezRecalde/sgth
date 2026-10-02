'use client'

import { PasswordInput, Button, Stack } from '@mantine/core'
import { IconLogout } from '@tabler/icons-react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  cambiarPasswordSchema,
  type CambiarPasswordFormData,
} from '../schemas/cambiarPassword.schema'
import { useCambiarPassword } from '../hooks/useCambiarPassword'
import { useCerrarSesion } from '../hooks/useCerrarSesion'
import { RequisitosContrasena } from './RequisitosContrasena'

export function CambiarPasswordForm() {
  const { mutate, isPending } = useCambiarPassword()
  const { cerrarSesion, cerrando } = useCerrarSesion()
  const contained = useContainedInput()

  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<CambiarPasswordFormData>({
    resolver: zodResolver(cambiarPasswordSchema),
    defaultValues: {
      nueva_contrasena:     '',
      confirmar_contrasena: '',
    },
  })

  const nuevaContrasena = useWatch({ control, name: 'nueva_contrasena' })

  return (
    <form onSubmit={handleSubmit((v) => mutate(v))} noValidate>
      <Stack gap="md">
        {/* `new-password` hace que el gestor de contraseñas proponga una
            nueva en vez de rellenar la cédula que acaba de guardar. */}
        <PasswordInput
          label="Nueva contraseña"
          autoComplete="new-password"
          autoFocus
          {...contained}
          {...register('nueva_contrasena')}
          error={errors.nueva_contrasena?.message}
        />
        <RequisitosContrasena contrasena={nuevaContrasena} />
        <PasswordInput
          label="Confirmar nueva contraseña"
          placeholder="Repita la nueva contraseña"
          autoComplete="new-password"
          {...contained}
          {...register('confirmar_contrasena')}
          error={errors.confirmar_contrasena?.message}
        />
        <Button
          type="submit"
          loading={isPending}
          disabled={cerrando}
          size="md"
          fullWidth
          mt="md"
        >
          Cambiar contraseña
        </Button>
        {/* Sin esto la pantalla era un callejón: el proxy no deja ir a
            ninguna otra, y quien no quería o no podía cambiarla en ese
            momento solo salía borrando las cookies. */}
        <Button
          variant="subtle"
          color="slate"
          fullWidth
          leftSection={<IconLogout size={16} />}
          loading={cerrando}
          disabled={isPending}
          onClick={cerrarSesion}
        >
          Cerrar sesión
        </Button>
      </Stack>
    </form>
  )
}
