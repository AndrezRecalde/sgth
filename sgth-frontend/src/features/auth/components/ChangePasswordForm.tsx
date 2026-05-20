'use client'

import { PasswordInput, Button, Stack, Text, Group } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { z } from 'zod/v4'
import { useCambiarPassword } from '../hooks/useCambiarPassword'

const schema = z.object({
  nueva_contrasena: z.string().min(8, 'La contraseña debe tener al menos 8 caracteres'),
  confirmar_contrasena: z.string()
}).refine((data) => data.nueva_contrasena === data.confirmar_contrasena, {
  message: 'Las contraseñas no coinciden',
  path: ['confirmar_contrasena'],
})

type FormValues = z.infer<typeof schema>

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

export function ChangePasswordForm() {
  const { mutate, isPending } = useCambiarPassword()

  const form = useForm<FormValues>({
    initialValues: {
      nueva_contrasena: '',
      confirmar_contrasena: '',
    },
    validate: zodResolver(schema),
  })

  const handleSubmit = (values: FormValues) => {
    mutate({ nueva_contrasena: values.nueva_contrasena })
  }

  return (
    <form onSubmit={form.onSubmit(handleSubmit)}>
      <Stack gap="md">
        <div>
          <Text size="sm" mb="xs">
            Por razones de seguridad, es necesario que actualice su contraseña antes de continuar utilizando el sistema.
          </Text>
        </div>

        <PasswordInput
          label="Nueva contraseña"
          placeholder="Ingrese su nueva contraseña"
          variant="filled"
          styles={containedInputStyles}
          {...form.getInputProps('nueva_contrasena')}
        />

        <PasswordInput
          label="Confirmar contraseña"
          placeholder="Repita su nueva contraseña"
          variant="filled"
          styles={containedInputStyles}
          {...form.getInputProps('confirmar_contrasena')}
        />

        <Group justify="flex-end" mt="xl">
          <Button type="submit" loading={isPending} size="md" w="100%">
            Cambiar y Continuar
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
