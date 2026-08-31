'use client'

import { PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  cambiarPasswordSchema,
  type CambiarPasswordFormData,
} from '../schemas/cambiarPassword.schema'
import { useCambiarPassword } from '../hooks/useCambiarPassword'

export function CambiarPasswordForm() {
  const { mutate, isPending } = useCambiarPassword()
  const contained = useContainedInput()

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CambiarPasswordFormData>({
    resolver: zodResolver(cambiarPasswordSchema),
    defaultValues: {
      nueva_contrasena:     '',
      confirmar_contrasena: '',
    },
  })

  return (
    <form noValidate onSubmit={handleSubmit((v) => mutate(v))}>
      <Stack gap="md">
        <PasswordInput
          label="Nueva contraseña"
          placeholder="Mínimo 8 caracteres con letras y números"
          {...contained}
          {...register('nueva_contrasena')}
          error={errors.nueva_contrasena?.message}
        />
        <PasswordInput
          label="Confirmar nueva contraseña"
          placeholder="Repita la nueva contraseña"
          {...contained}
          {...register('confirmar_contrasena')}
          error={errors.confirmar_contrasena?.message}
        />
        <Button
          type="submit"
          loading={isPending}
          size="md"
          fullWidth
          mt="md"
        >
          Cambiar contraseña
        </Button>
      </Stack>
    </form>
  )
}
