'use client'

import {
  Stack, TextInput, Textarea, Grid, Select,
  Checkbox, Group, Button, Modal,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { empleoAnteriorSchema, type EmpleoAnteriorForm } from '../../schemas/femo.schema'
import { TIPO_EVENTO_LABORAL_OPTIONS } from '../../services/femoOptions'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

interface Props {
  opened:  boolean
  onClose: () => void
  onAgregar: (values: EmpleoAnteriorForm) => void
}

export function FemoEmpleoAnteriorModal({ opened, onClose, onAgregar }: Props) {
  const contained = useContainedInput()

  const empleoForm = useForm<EmpleoAnteriorForm>({
    resolver: zodResolver(empleoAnteriorSchema),
    defaultValues: {
      centro_trabajo: '',
      actividades_desempenadas: '',
      es_trabajo_actual: false,
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
          {/* Columna «TRABAJO: ANTERIOR / ACTUAL» del formulario 028. La
              sección registra el historial laboral e incluye el empleo vigente,
              así que hay que poder distinguirlo. */}
          <Controller
            name="es_trabajo_actual"
            control={empleoForm.control}
            render={({ field }) => (
              <Checkbox
                label="Es el trabajo actual"
                description="Déjalo sin marcar si es un empleo anterior"
                checked={field.value ?? false}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Grid>
            <Grid.Col span={6}>
              <DatePickerInput
                label="Fecha inicio"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(empleoForm.watch('fecha_inicio'))}
                onChange={(d) =>
                  empleoForm.setValue('fecha_inicio', fromDateValueOrNull(d as Date | null))
                }
              />
            </Grid.Col>
            <Grid.Col span={6}>
              <DatePickerInput
                label="Fecha fin"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(empleoForm.watch('fecha_fin'))}
                onChange={(d) =>
                  empleoForm.setValue('fecha_fin', fromDateValueOrNull(d as Date | null))
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
                  value={toDateValue(empleoForm.watch('fecha_evento'))}
                  onChange={(d) =>
                    empleoForm.setValue('fecha_evento', fromDateValueOrNull(d as Date | null))
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
