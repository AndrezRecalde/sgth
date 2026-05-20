'use client'

import { PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconLock } from '@tabler/icons-react'
import { cambiarPasswordSchema } from '../schemas/cambiarPassword.schema'
import type { CambiarPasswordFormData } from '../schemas/cambiarPassword.schema'
import { useCambiarPassword } from '../hooks/useCambiarPassword'
import { containedInputStyles } from '../styles/authInputStyles'

export function CambiarPasswordForm() {
  const { mutate, isPending } = useCambiarPassword()

  const form = useForm<CambiarPasswordFormData>({
    initialValues: {
      nueva_contrasena: '',
      confirmar_contrasena: '',
    },
    validate: zodResolver(cambiarPasswordSchema),
  })

  const handleSubmit = (values: CambiarPasswordFormData) => {
    mutate(values)
  }

  return (
    <form onSubmit={form.onSubmit(handleSubmit)}>
      <Stack gap="md">
        <PasswordInput
          label="Nueva contraseña"
          placeholder="Ingrese nueva contraseña"
          variant="filled"
          leftSection={<IconLock size={16} />}
          styles={containedInputStyles}
          {...form.getInputProps('nueva_contrasena')}
        />
        <PasswordInput
          label="Confirmar nueva contraseña"
          placeholder="Repita la nueva contraseña"
          variant="filled"
          leftSection={<IconLock size={16} />}
          styles={containedInputStyles}
          {...form.getInputProps('confirmar_contrasena')}
        />
        <Button type="submit" loading={isPending} size="md" w="100%" mt="md">
          Cambiar Contraseña
        </Button>
      </Stack>
    </form>
  )
}
