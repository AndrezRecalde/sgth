'use client'

import {
  Modal, Stack, Text, NumberInput,
  Textarea, Button, Group, Card,
  Badge, Progress, Divider, Alert, Grid,
} from '@mantine/core'
import {
  IconCheck, IconTrophy, IconInfoCircle,
} from '@tabler/icons-react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCalificarPostulante } from '../hooks/useConvocatoria'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  opened:         boolean
  onClose:        () => void
  postulante:     Postulante | null
  convocatoriaId: number
}

const schema = z.object({
  puntaje_meritos:   z.number().min(0).max(40),
  puntaje_oposicion: z.number().min(0).max(60),
  observaciones:     z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

export function CalificarPostulanteModal({
  opened, onClose, postulante, convocatoriaId,
}: Props) {
  const contained  = useContainedInput()
  const calificar  = useCalificarPostulante(convocatoriaId)

  const {
    control, register, handleSubmit, watch, reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      puntaje_meritos:   postulante?.evaluacion?.puntaje_meritos ?? 0,
      puntaje_oposicion: postulante?.evaluacion?.puntaje_oposicion ?? 0,
      observaciones:     null,
    },
  })

  const meritos   = watch('puntaje_meritos') ?? 0
  const oposicion = watch('puntaje_oposicion') ?? 0
  const total     = Number(meritos) + Number(oposicion)
  const aprueba   = total >= 70

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: FormData) => {
    if (!postulante) return
    calificar.mutate(
      { postulanteId: postulante.id, data: values },
      { onSuccess: handleClose }
    )
  }

  if (!postulante) return null

  const nombreCompleto = [
    postulante.apellidos,
    postulante.segundo_apellido,
    postulante.nombres,
    postulante.segundo_nombre,
  ].filter(Boolean).join(' ')

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Calificar candidato"
      size="md"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">
          <Card withBorder radius="md" p="sm">
            <Stack gap={4}>
              <Text size="sm" fw={600}>{nombreCompleto}</Text>
              <Text size="xs" c="dimmed">{postulante.cedula}</Text>
              <Text size="xs" c="dimmed">{postulante.correo}</Text>
            </Stack>
          </Card>

          <Alert color="blue" variant="light"
            icon={<IconInfoCircle size={16} />}>
            <Text size="xs">
              Méritos (máx. 40 pts) + Oposición (máx. 60 pts).
              El candidato aprueba con puntaje total ≥ 70 puntos.
            </Text>
          </Alert>

          <Grid>
            <Grid.Col span={6}>
              <Controller
                name="puntaje_meritos"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Méritos"
                    description="Máximo 40 puntos"
                    min={0}
                    max={40}
                    decimalScale={2}
                    required
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(Number(v) || 0)
                    }
                    error={errors.puntaje_meritos?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={6}>
              <Controller
                name="puntaje_oposicion"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Oposición"
                    description="Máximo 60 puntos"
                    min={0}
                    max={60}
                    decimalScale={2}
                    required
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(Number(v) || 0)
                    }
                    error={errors.puntaje_oposicion?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          <Divider label="Resultado" labelPosition="center" />

          <Stack gap="xs">
            <Group justify="space-between">
              <Text size="sm" fw={600}>Puntaje total</Text>
              <Badge
                size="lg"
                color={aprueba ? 'emerald' : 'red'}
                variant="light"
                leftSection={aprueba
                  ? <IconTrophy size={12} />
                  : undefined}
              >
                {total.toFixed(2)} / 100
              </Badge>
            </Group>
            <Progress
              value={total}
              color={aprueba ? 'emerald' : 'red'}
              size="md"
              radius="xl"
            />
            <Text size="xs" c={aprueba ? 'emerald' : 'red'} ta="center">
              {aprueba
                ? '✓ Aprueba el proceso de selección'
                : '✗ No alcanza el puntaje mínimo (70 puntos)'}
            </Text>
          </Stack>

          <Textarea
            label="Observaciones"
            placeholder="Notas adicionales sobre la evaluación (opcional)"
            autosize
            minRows={2}
            {...contained}
            {...register('observaciones')}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={calificar.isPending}
            >
              Guardar calificación
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
