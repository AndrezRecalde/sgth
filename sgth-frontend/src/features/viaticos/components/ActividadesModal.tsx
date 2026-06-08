'use client'

import {
  Modal, Stack, Card, Text, Group,
  Button, Grid, Textarea, ActionIcon,
  Divider, Badge, Alert, ThemeIcon, TextInput,
} from '@mantine/core'
import { DatePickerInput, TimeInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import {
  IconPlus, IconTrash, IconClipboardList,
  IconAlertCircle, IconCheck,
} from '@tabler/icons-react'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import type { Viatico } from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  viatico:  Viatico
  onGuardar: (actividades: ActividadData[]) => void
  valorInicial?: ActividadData[]
}

export interface ActividadData {
  fecha:       string
  hora_inicio: string
  hora_fin:    string
  descripcion: string
  lugar:       string
}

const actividadItemSchema = z.object({
  fecha:       z.string().min(1, 'Seleccione la fecha'),
  hora_inicio: z.string().min(1, 'Requerido'),
  hora_fin:    z.string().min(1, 'Requerido'),
  descripcion: z.string().min(5, 'Mínimo 5 caracteres'),
  lugar:       z.string().min(1, 'Requerido'),
})

const schema = z.object({
  actividades: z.array(actividadItemSchema)
    .min(1, 'Agregue al menos una actividad'),
})

type FormData = z.infer<typeof schema>

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const safeFormatDate = (v: any): string => {
  if (!v) return ''
  const d = new Date(v)
  if (isNaN(d.getTime())) return ''
  
  if (d.getUTCHours() === 0 && d.getUTCMinutes() === 0) {
    return d.toISOString().slice(0, 10)
  }
  
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function ActividadesModal({
  opened,
  onClose,
  viatico,
  onGuardar,
  valorInicial = [],
}: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()

  // Rango de fechas permitido: salida → llegada del viático
  const minFecha = viatico.datetime_salida
    ? (() => {
        const d = new Date(viatico.datetime_salida as string)
        d.setHours(0, 0, 0, 0)
        return d
      })()
    : undefined
  const maxFecha = viatico.datetime_llegada
    ? (() => {
        const d = new Date(viatico.datetime_llegada as string)
        d.setHours(23, 59, 59, 999)
        return d
      })()
    : undefined

  const {
    control,
    handleSubmit,
    register,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      actividades: valorInicial.length > 0
        ? valorInicial
        : [{
            fecha:       '',
            hora_inicio: '08:00',
            hora_fin:    '17:00',
            descripcion: '',
            lugar:       '',
          }],
    },
  })

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'actividades',
  })

  const onSubmit = (values: FormData) => {
    onGuardar(values.actividades)
    onClose()
  }

  const formatFechaRango = (f?: string | null) => {
    if (!f) return '—'
    return new Date(f).toLocaleDateString('es-EC', {
      timeZone: 'UTC',
      day: '2-digit', month: 'long', year: 'numeric',
    })
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="sm">
            <IconClipboardList size={14} />
          </ThemeIcon>
          <Text fw={600}>Informe de actividades</Text>
        </Group>
      }
      size="xl"
      radius="xl"
      fullScreen={isMobile}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">

          {/* Alerta de rango de fechas */}
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="blue"
            variant="light"
          >
            <Text size="xs" fw={500}>
              Período del viático
            </Text>
            <Text size="xs" mt={2}>
              Solo puede registrar actividades entre el{' '}
              <strong>
                {formatFechaRango(
                  viatico.datetime_salida as string
                )}
              </strong>
              {' '}y el{' '}
              <strong>
                {formatFechaRango(
                  viatico.datetime_llegada as string
                )}
              </strong>.
              Los días fuera de este rango no estarán disponibles.
            </Text>
          </Alert>

          {/* Lista de actividades */}
          <Stack gap="sm">
            {fields.map((field, i) => (
              <Card
                key={field.id}
                withBorder
                radius="md"
                p="sm"
              >
                <Group justify="space-between" mb="xs">
                  <Group gap="xs">
                    <Badge
                      size="sm"
                      color="blue"
                      variant="light"
                      circle
                    >
                      {i + 1}
                    </Badge>
                    <Text size="sm" fw={600}>
                      Actividad {i + 1}
                    </Text>
                  </Group>
                  {fields.length > 1 && (
                    <ActionIcon
                      size="sm"
                      color="red"
                      variant="subtle"
                      onClick={() => remove(i)}
                    >
                      <IconTrash size={13} />
                    </ActionIcon>
                  )}
                </Group>

                <Grid>
                  <Grid.Col span={{ base: 12, sm: 4 }}>
                    <Controller
                      name={`actividades.${i}.fecha`}
                      control={control}
                      render={({ field: f }) => (
                        <DatePickerInput
                          label="Fecha de la actividad"
                          placeholder="Seleccionar"
                          valueFormat="DD/MM/YYYY"
                          minDate={minFecha}
                          maxDate={maxFecha}
                          popoverProps={{ withinPortal: true }}
                          {...contained}
                          value={toDate(f.value)}
                          onChange={(v) =>
                            f.onChange(safeFormatDate(v))
                          }
                          error={
                            errors.actividades?.[i]
                              ?.fecha?.message
                          }
                        />
                      )}
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 6, sm: 4 }}>
                    <TimeInput
                      label="Hora inicio"
                      {...contained}
                      {...register(
                        `actividades.${i}.hora_inicio`
                      )}
                      error={
                        errors.actividades?.[i]
                          ?.hora_inicio?.message
                      }
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 6, sm: 4 }}>
                    <TimeInput
                      label="Hora fin"
                      {...contained}
                      {...register(
                        `actividades.${i}.hora_fin`
                      )}
                      error={
                        errors.actividades?.[i]
                          ?.hora_fin?.message
                      }
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name={`actividades.${i}.lugar`}
                      control={control}
                      render={({ field: f }) => (
                        <TextInput
                          label="Lugar"
                          placeholder="Ej: Ministerio de Trabajo — Quito"
                          {...contained}
                          value={f.value}
                          onChange={(e) =>
                            f.onChange(e.currentTarget.value)
                          }
                          error={
                            errors.actividades?.[i]
                              ?.lugar?.message
                          }
                        />
                      )}
                    />
                  </Grid.Col>
                </Grid>

                <Textarea
                  label="Descripción de la actividad"
                  placeholder="Describa las actividades realizadas en este día"
                  autosize
                  minRows={2}
                  maxRows={4}
                  mt="xs"
                  {...contained}
                  {...register(`actividades.${i}.descripcion`)}
                  error={
                    errors.actividades?.[i]?.descripcion?.message
                  }
                />
              </Card>
            ))}
          </Stack>

          <Button
            variant="light"
            color="blue"
            size="sm"
            leftSection={<IconPlus size={14} />}
            onClick={() => append({
              fecha:       '',
              hora_inicio: '08:00',
              hora_fin:    '17:00',
              descripcion: '',
              lugar:       '',
            })}
          >
            Agregar otro día de actividades
          </Button>

          <Divider />

          <Group justify="flex-end">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="blue"
              leftSection={<IconCheck size={14} />}
            >
              Guardar actividades ({fields.length})
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
