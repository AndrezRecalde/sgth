'use client'

import { useState } from 'react'
import {
  Stack, Grid, TextInput, Select,
  Textarea, NumberInput, Button,
  Group, Card, Text, Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { IconSpeakerphone, IconArrowLeft } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCrearConvocatoria } from
  '@/features/seleccion/hooks/useConvocatoria'
import { TIPO_CONVOCATORIA_OPTIONS } from
  '@/features/seleccion/services/convocatoriaService'

const schema = z.object({
  puesto_id:    z.number({ error: 'Seleccione un puesto' }),
  titulo:       z.string().min(5, 'Mínimo 5 caracteres'),
  descripcion:  z.string().min(10, 'Mínimo 10 caracteres'),
  tipo:         z.enum(['interna', 'externa', 'mixta']),
  vacantes:     z.number().min(1, 'Mínimo 1 vacante'),
  fecha_inicio: z.string().min(1, 'Requerido'),
  fecha_fin:    z.string().min(1, 'Requerido'),
})

type FormData = z.infer<typeof schema>

function fromDate(d: Date | string | null): string {
  if (!d) return ''
  if (typeof d === 'string') return d.slice(0, 10)
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}

export default function NuevaConvocatoriaPage() {
  const router   = useRouter()
  const contained = useContainedInput()
  const crear    = useCrearConvocatoria()

  const {
    control, register, handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo:     'externa',
      vacantes: 1,
    },
  })

  const onSubmit = (values: FormData) => {
    crear.mutate(values, {
      onSuccess: (conv) =>
        router.push(
          `/sgth/reclutamiento/convocatorias/${conv.id}`
        ),
    })
  }

  return (
    <Stack gap="md">
      <PageHeader
        title="Nueva convocatoria"
        subtitle="Crear proceso de selección e incorporación"
        icon={<IconSpeakerphone size={24} />}
      />

      <form onSubmit={handleSubmit(onSubmit)}>
        <Card withBorder radius="lg" p="lg">
          <Stack gap="md">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Información general
            </Text>

            <TextInput
              label="Título de la convocatoria"
              placeholder="Ej: Concurso de méritos y oposición — Técnico Electricista SP3"
              required
              {...contained}
              {...register('titulo')}
              error={errors.titulo?.message}
            />

            <Textarea
              label="Descripción"
              placeholder="Descripción del proceso, requisitos generales y perfil del cargo"
              required
              autosize
              minRows={3}
              {...contained}
              {...register('descripcion')}
              error={errors.descripcion?.message}
            />

            <Grid>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="tipo"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Tipo de convocatoria"
                      data={TIPO_CONVOCATORIA_OPTIONS}
                      required
                      {...contained}
                      value={field.value}
                      onChange={(v) =>
                        field.onChange(v ?? 'externa')
                      }
                      error={errors.tipo?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="vacantes"
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label="Número de vacantes"
                      min={1}
                      required
                      {...contained}
                      value={field.value}
                      onChange={(v) =>
                        field.onChange(Number(v) || 1)
                      }
                      error={errors.vacantes?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="puesto_id"
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label="ID del puesto"
                      description="Ingrese el ID del puesto del sistema"
                      min={1}
                      required
                      {...contained}
                      value={field.value ?? undefined}
                      onChange={(v) =>
                        field.onChange(Number(v) || undefined)
                      }
                      error={errors.puesto_id?.message}
                    />
                  )}
                />
              </Grid.Col>
            </Grid>

            <Divider label="Período de la convocatoria" />

            <Grid>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <Controller
                  name="fecha_inicio"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha de inicio"
                      valueFormat="DD/MM/YYYY"
                      required
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d) =>
                        field.onChange(fromDate(d as Date | null))
                      }
                      error={errors.fecha_inicio?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <Controller
                  name="fecha_fin"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha de cierre"
                      valueFormat="DD/MM/YYYY"
                      required
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d) =>
                        field.onChange(fromDate(d as Date | null))
                      }
                      error={errors.fecha_fin?.message}
                    />
                  )}
                />
              </Grid.Col>
            </Grid>
          </Stack>
        </Card>

        <Group justify="space-between" mt="md">
          <Button
            variant="default"
            leftSection={<IconArrowLeft size={14} />}
            onClick={() =>
              router.push('/sgth/reclutamiento/convocatorias')
            }
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            color="emerald"
            leftSection={<IconSpeakerphone size={14} />}
            loading={crear.isPending}
          >
            Crear convocatoria
          </Button>
        </Group>
      </form>
    </Stack>
  )
}
