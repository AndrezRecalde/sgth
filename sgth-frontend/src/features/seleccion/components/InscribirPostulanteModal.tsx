'use client'

import {
  Modal, Stack, TextInput, Button,
  Group, Text, Alert,
} from '@mantine/core'
import { IconUsers, IconCheck, IconInfoCircle } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useInscribirPostulante } from '../hooks/useConvocatoria'

interface Props {
  opened:          boolean
  onClose:         () => void
  convocatoriaId:  number
}

const schema = z.object({
  cedula:    z.string().min(8, 'Cédula inválida').max(20),
  nombres:   z.string().min(2, 'Ingrese los nombres'),
  apellidos: z.string().min(2, 'Ingrese los apellidos'),
  correo:    z.email('Correo inválido'),
  telefono:  z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

export function InscribirPostulanteModal({
  opened, onClose, convocatoriaId,
}: Props) {
  const contained = useContainedInput()
  const inscribir = useInscribirPostulante(convocatoriaId)

  const {
    register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: FormData) => {
    inscribir.mutate(values, {
      onSuccess: handleClose,
    })
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Inscribir candidato"
      size="md"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Alert
            color="blue"
            variant="light"
            icon={<IconInfoCircle size={16} />}
          >
            <Text size="xs">
              Ingrese los datos del candidato tal como
              aparecen en su cédula de ciudadanía.
            </Text>
          </Alert>

          <TextInput
            label="Cédula de ciudadanía"
            placeholder="0802704171"
            required
            {...contained}
            {...register('cedula')}
            error={errors.cedula?.message}
          />

          <Group grow>
            <TextInput
              label="Nombres"
              placeholder="Nombres del candidato"
              required
              {...contained}
              {...register('nombres')}
              error={errors.nombres?.message}
            />
            <TextInput
              label="Apellidos"
              placeholder="Apellidos del candidato"
              required
              {...contained}
              {...register('apellidos')}
              error={errors.apellidos?.message}
            />
          </Group>

          <TextInput
            label="Correo electrónico"
            placeholder="candidato@correo.com"
            description="Se usará para enviar notificaciones del proceso"
            required
            {...contained}
            {...register('correo')}
            error={errors.correo?.message}
          />

          <TextInput
            label="Teléfono"
            placeholder="0991234567"
            {...contained}
            {...register('telefono')}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={inscribir.isPending}
            >
              Inscribir candidato
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
