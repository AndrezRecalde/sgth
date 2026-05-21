'use client'

import { PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  cambiarPasswordSchema,
  type CambiarPasswordFormData,
} from '../schemas/cambiarPassword.schema'
import { useCambiarPassword } from '../hooks/useCambiarPassword'

export function CambiarPasswordForm() {
  const { mutate, isPending } = useCambiarPassword()
  const contained = useContainedInput()

  const form = useForm<CambiarPasswordFormData>({
    initialValues: {
      nueva_contrasena: '',
      confirmar_contrasena: '',
    },
    validate: zodResolver(cambiarPasswordSchema),
  })

  return (
    <form onSubmit={form.onSubmit((v) => mutate(v))}>
      <Stack gap="md">
        <PasswordInput
          label="Nueva contraseña"
          placeholder="Mínimo 8 caracteres con letras y números"
          {...contained}
          {...form.getInputProps('nueva_contrasena')}
        />
        <PasswordInput
          label="Confirmar nueva contraseña"
          placeholder="Repita la nueva contraseña"
          {...contained}
          {...form.getInputProps('confirmar_contrasena')}
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
