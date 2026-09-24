'use client'

import {
  Stack, NumberInput, TextInput,
  Textarea, Text,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useEffect } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAccionesItem } from '../hooks/useReceta'
import { nombreDeItem } from '../services/recetaService'
import {
  itemRecetaSchema, type ItemRecetaFormData,
} from '../schemas/itemReceta.schema'
import type { ItemReceta } from '../services/recetaService'

interface Props {
  opened:    boolean
  onClose:   () => void
  item:      ItemReceta | null
  recetaId:  number
  consultaId: number
}

export function EditarItemRecetaModal({
  opened, onClose, item, recetaId, consultaId,
}: Props) {
  const contained = useContainedInput()
  const { actualizarItem } = useAccionesItem(consultaId)

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<ItemRecetaFormData>({
    resolver: zodResolver(itemRecetaSchema),
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

  const onSubmit = (values: ItemRecetaFormData) => {
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
          render={({ field }) => (
            <NumberInput
              label="Cantidad"
              min={1}
              // Unidades de un medicamento: no hay media cápsula, y el
              // servidor lo exige entero. Mantine deja decimales por defecto.
              allowDecimal={false}
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
          {...register('dosis')}
          error={errors.dosis?.message}
        />
        <TextInput
          label="Frecuencia"
          placeholder="Ej: Cada 8 horas"
          required
          {...contained}
          {...register('frecuencia')}
          error={errors.frecuencia?.message}
        />
        <TextInput
          label="Duración"
          placeholder="Ej: 5 días"
          required
          {...contained}
          {...register('duracion')}
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
