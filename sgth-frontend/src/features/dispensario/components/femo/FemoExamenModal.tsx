'use client'

import { Stack, TextInput, Select, Textarea, Group, Button, Modal } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, useWatch, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { examenSchema, type ExamenForm } from '../../schemas/femo.schema'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

interface Props {
  opened:  boolean
  onClose: () => void
  onAgregar: (values: ExamenForm) => void
}

const TIPO_EXAMEN_OPTIONS = [
  { value: 'laboratorio', label: 'Laboratorio' },
  { value: 'imagen',      label: 'Imagen'      },
  { value: 'otro',        label: 'Otro'        },
]

export function FemoExamenModal({ opened, onClose, onAgregar }: Props) {
  const contained = useContainedInput()

  const examenForm = useForm<ExamenForm>({
    resolver: zodResolver(examenSchema),
    defaultValues: {
      nombre_examen: '',
      resultado:     null,
      fecha_examen:  null,
      tipo:          'laboratorio',
    },
  })

  const fechaExamen = useWatch({
    control: examenForm.control,
    name: 'fecha_examen',
  })

  const handleSubmit = (values: ExamenForm) => {
    onAgregar(values)
    examenForm.reset()
    onClose()
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Agregar examen complementario" size="md" radius="xl">
      <form onSubmit={examenForm.handleSubmit(handleSubmit)} noValidate>
        <Stack gap="sm">
          <Controller
            name="tipo"
            control={examenForm.control}
            render={({ field }) => (
              <Select
                label="Tipo de examen"
                data={TIPO_EXAMEN_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'laboratorio')}
              />
            )}
          />
          <TextInput
            label="Nombre del examen"
            required
            placeholder="Ej: Hemograma completo"
            {...contained}
            {...examenForm.register('nombre_examen')}
            error={examenForm.formState.errors.nombre_examen?.message}
          />
          <Textarea
            label="Resultado"
            autosize
            minRows={2}
            {...contained}
            {...examenForm.register('resultado')}
          />
          <DatePickerInput
            label="Fecha del examen"
            valueFormat="DD/MM/YYYY"
            clearable
            {...contained}
            value={toDateValue(fechaExamen)}
            onChange={(d) =>
              examenForm.setValue('fecha_examen', fromDateValueOrNull(d as Date | null))
            }
          />
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
