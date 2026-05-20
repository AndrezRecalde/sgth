'use client'

import { TextInput, PasswordInput, Button, Stack, Text, Anchor } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconLogin } from '@tabler/icons-react'
import { loginSchema, type LoginFormData } from '../schemas/login.schema'
import { useLogin } from '../hooks/useLogin'
import { containedInputStyles } from '../styles/authInputStyles'

export function LoginForm() {
  const { mutate, isPending } = useLogin()
  const form = useForm<LoginFormData>({
    initialValues: { usuario: '', contrasena: '' },
    validate: zodResolver(loginSchema),
  })

  return (
    <form onSubmit={form.onSubmit((v) => mutate(v))}>
      <Stack gap="sm">
        <TextInput
          label="Usuario"
          placeholder="Ingrese su usuario"
          styles={containedInputStyles}
          {...form.getInputProps('usuario')}
        />
        <PasswordInput
          label="Contraseña"
          placeholder="Ingrese su contraseña"
          styles={containedInputStyles}
          {...form.getInputProps('contrasena')}
        />
        <Button
          type="submit"
          fullWidth
          mt="xs"
          loading={isPending}
          leftSection={<IconLogin size={16} />}
        >
          Iniciar sesión
        </Button>
      </Stack>
    </form>
  )
}
