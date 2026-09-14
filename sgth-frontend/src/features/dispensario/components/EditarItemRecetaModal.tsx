'use client'

import {
  Stack, NumberInput, TextInput,
  Textarea, Text,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useEffect } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAccionesItem } from '../hooks/useReceta'
import { nombreDeItem } from '../services/recetaService'
import type { ItemReceta } from '../services/recetaService'

interface Props {
  opened:    boolean
  onClose:   () => void
  item:      ItemReceta | null
  recetaId:  number
  consultaId: number
}

type FormData = {
  cantidad_prescrita: number
  dosis:              string
  frecuencia:         string
  duracion:           string
  observaciones:      string
}

export function EditarItemRecetaModal({
  opened, onClose, item, recetaId, consultaId,
}: Props) {
  const contained = useContainedInput()
  const { actualizarItem } = useAccionesItem(consultaId)

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: {
      cantidad_prescrita: 1,
      dosis:              '',
      frecuencia:         '',
      duracion:           '',
      observaciones:      '',
    },
  })

  useEffect(() => {
    if (opened && item) {
      reset({
        cantidad_prescrita: item.cantidad_prescrita ?? 1,
        dosis:              item.dosis ?? '',
        frecuencia:         item.frecuencia ?? '',
        duracion:           item.duracion ?? '',
        observaciones:      item.observaciones ?? '',
      })
    }
  }, [opened, item, reset])

  const onSubmit = (values: FormData) => {
    if (!item?.id) return
    actualizarItem.mutate(
      {
        recetaId,
        itemId: item.id,
        data: {
          ...values,
          observaciones: values.observaciones || null,
        },
      },
      { onSuccess: () => { reset(); onClose() } }
    )
  }

  if (!item) return null

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={
        <Text size="sm" fw={600}>
          Editar medicamento
          <Text span c="dimmed" ml={4}>
            — {nombreDeItem(item)}
          </Text>
        </Text>
      }
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar cambios"
      submitting={actualizarItem.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="cantidad_prescrita"
          control={control}
          rules={{
            required: 'Indique la cantidad',
            min: { value: 1, message: 'Debe ser al menos 1' },
          }}
          render={({ field }) => (
            <NumberInput
              label="Cantidad"
              min={1}
              required
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(Number(v) || 1)}
              error={errors.cantidad_prescrita?.message}
            />
          )}
        />
        <TextInput
          label="Dosis"
          placeholder="Ej: 1 tableta"
          required
          {...contained}
          {...register('dosis', { required: 'Indique la dosis' })}
          error={errors.dosis?.message}
        />
        <TextInput
          label="Frecuencia"
          placeholder="Ej: Cada 8 horas"
          required
          {...contained}
          {...register('frecuencia', { required: 'Indique la frecuencia' })}
          error={errors.frecuencia?.message}
        />
        <TextInput
          label="Duración"
          placeholder="Ej: 5 días"
          required
          {...contained}
          {...register('duracion', { required: 'Indique la duración' })}
          error={errors.duracion?.message}
        />
        <Textarea
          label="Observaciones (opcional)"
          autosize
          minRows={2}
          {...contained}
          {...register('observaciones')}
        />
      </Stack>
    </FormModal>
  )
}
