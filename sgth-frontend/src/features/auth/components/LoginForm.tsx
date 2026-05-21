'use client'

import { TextInput, PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconLogin } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { loginSchema, type LoginFormData } from '../schemas/login.schema'
import { useLogin } from '../hooks/useLogin'

export function LoginForm() {
  const { mutate, isPending } = useLogin()
  const contained = useContainedInput()

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
          {...contained}
          {...form.getInputProps('usuario')}
        />
        <PasswordInput
          label="Contraseña"
          placeholder="Ingrese su contraseña"
          {...contained}
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
