'use client'

import {
  Stack, TextInput, Textarea, Grid, Select,
  Checkbox, Group, Button, Modal,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { empleoAnteriorSchema, type EmpleoAnteriorForm } from '../../schemas/femo.schema'
import { TIPO_EVENTO_LABORAL_OPTIONS } from '../../services/femoOptions'

interface Props {
  opened:  boolean
  onClose: () => void
  onAgregar: (values: EmpleoAnteriorForm) => void
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  if (typeof d === 'string') return d.slice(0, 10)
  if (!(d instanceof Date) || isNaN(d.getTime())) return null
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

export function FemoEmpleoAnteriorModal({ opened, onClose, onAgregar }: Props) {
  const contained = useContainedInput()

  const empleoForm = useForm<EmpleoAnteriorForm>({
    resolver: zodResolver(empleoAnteriorSchema),
    defaultValues: {
      centro_trabajo: '',
      actividades_desempenadas: '',
      fecha_inicio: null,
      fecha_fin:    null,
      observaciones: null,
      tipo_evento_laboral: 'ninguno',
      calificado_iess: null,
      fecha_evento: null,
      especificar: null,
    },
  })

  const tipoEvento = empleoForm.watch('tipo_evento_laboral')

  const handleSubmit = (values: EmpleoAnteriorForm) => {
    onAgregar(values)
    empleoForm.reset()
    onClose()
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Agregar empleo anterior" size="md" radius="xl">
      <form onSubmit={empleoForm.handleSubmit(handleSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Centro de trabajo"
            required
            {...contained}
            {...empleoForm.register('centro_trabajo')}
            error={empleoForm.formState.errors.centro_trabajo?.message}
          />
          <Textarea
            label="Actividades desempeñadas"
            autosize
            minRows={2}
            {...contained}
            {...empleoForm.register('actividades_desempenadas')}
          />
          <Grid>
            <Grid.Col span={6}>
              <DatePickerInput
                label="Fecha inicio"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDate(empleoForm.watch('fecha_inicio'))}
                onChange={(d) =>
                  empleoForm.setValue('fecha_inicio', fromDate(d as Date | null))
                }
              />
            </Grid.Col>
            <Grid.Col span={6}>
              <DatePickerInput
                label="Fecha fin"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDate(empleoForm.watch('fecha_fin'))}
                onChange={(d) =>
                  empleoForm.setValue('fecha_fin', fromDate(d as Date | null))
                }
              />
            </Grid.Col>
          </Grid>
          <Textarea
            label="Observaciones"
            autosize
            minRows={2}
            {...contained}
            {...empleoForm.register('observaciones')}
          />
          <Controller
            name="tipo_evento_laboral"
            control={empleoForm.control}
            render={({ field }) => (
              <Select
                label="Tipo de evento laboral"
                data={TIPO_EVENTO_LABORAL_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'ninguno')}
              />
            )}
          />
          {tipoEvento !== 'ninguno' && (
            <>
              <Group grow>
                <Checkbox
                  label="Calificado por IESS"
                  checked={empleoForm.watch('calificado_iess') ?? false}
                  onChange={(e) =>
                    empleoForm.setValue('calificado_iess', e.currentTarget.checked)
                  }
                />
                <DatePickerInput
                  label="Fecha del evento"
                  valueFormat="DD/MM/YYYY"
                  clearable
                  {...contained}
                  value={toDate(empleoForm.watch('fecha_evento'))}
                  onChange={(d) =>
                    empleoForm.setValue('fecha_evento', fromDate(d as Date | null))
                  }
                />
              </Group>
              <Textarea
                label="Especificar"
                autosize
                minRows={2}
                {...contained}
                {...empleoForm.register('especificar')}
              />
            </>
          )}
          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" color="emerald" leftSection={<IconCheck size={14} />}>
              Agregar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
