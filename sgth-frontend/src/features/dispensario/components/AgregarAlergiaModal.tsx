'use client'

import {
  Stack, Select, TextInput,
  Textarea, 
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAgregarAlergia } from '../hooks/useHistoriaClinica'

interface Props {
  opened:      boolean
  onClose:     () => void
  historiaId:  number
  agendaId:    number
}

const TIPO_OPTIONS = [
  { value: 'medicamento', label: 'Medicamento' },
  { value: 'alimento',    label: 'Alimento'    },
  { value: 'ambiental',   label: 'Ambiental'   },
  { value: 'otro',        label: 'Otro'         },
]

const SEVERIDAD_OPTIONS = [
  { value: 'leve',     label: 'Leve'     },
  { value: 'moderada', label: 'Moderada' },
  { value: 'grave',    label: 'Grave'    },
]

type FormData = {
  tipo:        string
  descripcion: string
  severidad:   string
  observacion: string
}

export function AgregarAlergiaModal({
  opened, onClose, historiaId, agendaId,
}: Props) {
  const contained = useContainedInput()
  const agregar   = useAgregarAlergia(historiaId, agendaId)

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: {
      tipo: '', descripcion: '',
      severidad: '', observacion: '',
    },
  })

  const onSubmit = (values: FormData) => {
    agregar.mutate(
      {
        tipo:        values.tipo,
        descripcion: values.descripcion,
        severidad:   values.severidad,
        observacion: values.observacion || null,
      },
      { onSuccess: () => { reset(); onClose() } }
    )
  }

  return (
    <FormModal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title="Agregar alergia"
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Agregar alergia"
      submitting={agregar.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="tipo"
          control={control}
          rules={{ required: 'Seleccione el tipo de alergia' }}
          render={({ field }) => (
            <Select
              label="Tipo de alergia"
              required
              data={TIPO_OPTIONS}
              placeholder="Seleccione"
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? '')}
              error={errors.tipo?.message}
            />
          )}
        />
        <TextInput
          label="Descripción"
          placeholder="Ej: Penicilina, Mariscos, Polen..."
          {...contained}
          required
          {...register('descripcion', {
            required: 'Describa la alergia',
          })}
          error={errors.descripcion?.message}
        />
        <Controller
          name="severidad"
          control={control}
          rules={{ required: 'Seleccione la severidad' }}
          render={({ field }) => (
            <Select
              label="Severidad"
              required
              data={SEVERIDAD_OPTIONS}
              placeholder="Seleccione"
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? '')}
              error={errors.severidad?.message}
            />
          )}
        />
        <Textarea
          label="Observación (opcional)"
          placeholder="Detalles adicionales de la alergia"
          autosize
          minRows={2}
          {...contained}
          {...register('observacion')}
        />
      </Stack>
    </FormModal>
  )
}
