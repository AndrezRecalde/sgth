'use client'

import React from 'react'
import { TextInput, PasswordInput, Button, Stack, Title, Text, Paper, Container } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconUser, IconLock } from '@tabler/icons-react'
import { loginSchema, type LoginFormData } from '../schemas/login.schema'
import { useLogin } from '../hooks/useLogin'
import { containedInputStyles } from '../styles/authInputStyles'

export function LoginForm() {
  const { mutate, isPending } = useLogin()

  const form = useForm<LoginFormData>({
    initialValues: { usuario: '', contrasena: '' },
    validate: zodResolver(loginSchema),
  })

  const handleSubmit = (values: LoginFormData) => mutate(values)

  return (
    <Container size={420} my={40}>
      <Stack align="center" mb="xl">
        <Title order={1} size="42px" fw={800} c="emerald.6">SGTH</Title>
        <Text c="dimmed" size="sm" fw={500}>GAD Provincial de Esmeraldas</Text>
      </Stack>

      <Paper withBorder shadow="md" p={30} mt={30} radius="md">
        <form onSubmit={form.onSubmit(handleSubmit)}>
          <Stack gap="md">
            <TextInput
              label="Usuario"
              placeholder="Ingrese su usuario"
              variant="filled"
              leftSection={<IconUser size={16} />}
              styles={containedInputStyles}
              {...form.getInputProps('usuario')}
            />
            <PasswordInput
              label="Contraseña"
              placeholder="Su contraseña"
              variant="filled"
              leftSection={<IconLock size={16} />}
              styles={containedInputStyles}
              {...form.getInputProps('contrasena')}
            />
            <Button type="submit" fullWidth mt="xl" loading={isPending}>
              Iniciar sesión
            </Button>
          </Stack>
        </form>
      </Paper>
    </Container>
  )
}
