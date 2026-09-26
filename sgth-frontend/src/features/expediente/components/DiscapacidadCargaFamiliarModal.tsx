'use client'

import { Stack,
         Select, NumberInput, TextInput } from '@mantine/core'
import { FormModal, notificar } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQueryClient } from '@tanstack/react-query'
import { cargaFamiliarService } from '../services/cargaFamiliarService'
import React from 'react'

import { getApiErrorMessage } from '@/types/api'
const TIPO_OPTIONS = [
  { value: 'fisica',      label: 'Física' },
  { value: 'sensorial',   label: 'Sensorial (Visual / Auditiva)' },
  { value: 'intelectual', label: 'Intelectual' },
  { value: 'psicosocial', label: 'Psicosocial o Mental' },
  { value: 'visceral',    label: 'Visceral u Orgánica' },
  { value: 'multiple',    label: 'Múltiple' },
]

const schema = z.object({
  tipo_discapacidad:     z.enum([
    'fisica', 'sensorial', 'intelectual',
    'psicosocial', 'visceral', 'multiple',
  ]),
  porcentaje:            z.number().min(1, 'Mínimo 1%').max(100, 'Máximo 100%'),
  numero_carnet_conadis: z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

interface Props {
  opened:     boolean
  onClose:    () => void
  cargaId:    number
  servidorId: number
}

export function DiscapacidadCargaFamiliarModal({
  opened, onClose, cargaId, servidorId,
}: Props) {
  const contained    = useContainedInput()
  const qc           = useQueryClient()

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo_discapacidad:     'fisica',
      porcentaje:            1,
      numero_carnet_conadis: '',
    },
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = async (values: FormData) => {
    try {
      await cargaFamiliarService.crearDiscapacidadCarga(
        cargaId,
        values
      )
      notificar.exito(
        'Discapacidad registrada',
        'La discapacidad fue registrada correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
      handleClose()
    } catch (error) {
      notificar.error('No se pudo registrar la discapacidad', getApiErrorMessage(error))
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title="Registrar discapacidad"
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Registrar discapacidad"
      submitting={isSubmitting}
    >
      <Stack gap="sm">
        <Controller
          name="tipo_discapacidad"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de discapacidad"
              placeholder="Seleccionar tipo"
              data={TIPO_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? 'fisica')}
              error={errors.tipo_discapacidad?.message}
            />
          )}
        />
        <Controller
          name="porcentaje"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Porcentaje de discapacidad"
              placeholder="Ej: 45"
              min={1}
              max={100}
              suffix="%"
              {...contained}
              value={field.value}
              onChange={(v) =>
                field.onChange(typeof v === 'number' ? v : 1)
              }
              error={errors.porcentaje?.message}
            />
          )}
        />
        <TextInput
          label="Número de carnet CONADIS"
          placeholder="Número del carnet (opcional)"
          {...contained}
          {...register('numero_carnet_conadis')}
          error={errors.numero_carnet_conadis?.message}
        />
      </Stack>
    </FormModal>
  )
}

