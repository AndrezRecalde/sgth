'use client'

import { PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconLock } from '@tabler/icons-react'
import { cambiarPasswordSchema } from '../schemas/cambiarPassword.schema'
import type { CambiarPasswordFormData } from '../schemas/cambiarPassword.schema'
import { useCambiarPassword } from '../hooks/useCambiarPassword'

const containedInputStyles = {
  root: { },
  label: {
    fontSize: '10px',
    fontWeight: 500,
    color: 'var(--mantine-color-dimmed)',
    marginBottom: '2px',
  },
  input: {
    paddingTop: '18px',
    paddingBottom: '6px',
  },
  innerInput: {
    paddingTop: '12px',
  },
  wrapper: {
    position: 'relative',
  },
} as const

export function CambiarPasswordForm() {
  const { mutate, isPending } = useCambiarPassword()

  const form = useForm<CambiarPasswordFormData>({
    initialValues: {
      password_actual: '',
      password_nuevo: '',
      password_confirmacion: '',
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
          label="Contraseña actual"
          placeholder="Ingrese su contraseña actual"
          variant="filled"
          leftSection={<IconLock size={16} />}
          styles={containedInputStyles}
          {...form.getInputProps('password_actual')}
        />
        <PasswordInput
          label="Nueva contraseña"
          placeholder="Ingrese nueva contraseña"
          variant="filled"
          leftSection={<IconLock size={16} />}
          styles={containedInputStyles}
          {...form.getInputProps('password_nuevo')}
        />
        <PasswordInput
          label="Confirmar nueva contraseña"
          placeholder="Repita la nueva contraseña"
          variant="filled"
          leftSection={<IconLock size={16} />}
          styles={containedInputStyles}
          {...form.getInputProps('password_confirmacion')}
        />
        <Button type="submit" loading={isPending} size="md" w="100%" mt="md">
          Cambiar Contraseña
        </Button>
      </Stack>
    </form>
  )
}
