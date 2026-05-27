'use client'

import { TextInput, PasswordInput, Button, Stack } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconLogin } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { loginSchema, type LoginFormData } from '../schemas/login.schema'
import { useLogin } from '../hooks/useLogin'

export function LoginForm() {
  const { mutate, isPending } = useLogin()
  const contained = useContainedInput()

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: { usuario: '', contrasena: '' },
  })

  return (
    <form onSubmit={handleSubmit((v) => mutate(v))}>
      <Stack gap="sm">
        <TextInput
          label="Usuario"
          placeholder="Ingrese su usuario"
          {...contained}
          {...register('usuario')}
          error={errors.usuario?.message}
        />
        <PasswordInput
          label="Contraseña"
          placeholder="Ingrese su contraseña"
          {...contained}
          {...register('contrasena')}
          error={errors.contrasena?.message}
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
