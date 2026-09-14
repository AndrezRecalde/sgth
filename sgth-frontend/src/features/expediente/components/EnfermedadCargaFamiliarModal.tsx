'use client'

import { Stack, TextInput } from '@mantine/core'
import { FormModal, notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQueryClient } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'
import React from 'react'

const schema = z.object({
  tipo_enfermedad:   z.string().min(2, 'Mínimo 2 caracteres'),
  codigo_cie10:      z.string().optional().nullable(),
  fecha_diagnostico: z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

const toDate = (v?: string | null): Date | null =>
  v ? new Date(v) : null

const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = new Date(d)
  return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0]
}

interface Props {
  opened:     boolean
  onClose:    () => void
  cargaId:    number
  servidorId: number
}

export function EnfermedadCargaFamiliarModal({
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
      tipo_enfermedad:   '',
      codigo_cie10:      '',
      fecha_diagnostico: null,
    },
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = async (values: FormData) => {
    try {
      await expedienteService.crearEnfermedadCarga(
        cargaId,
        values
      )
      notificar.exito(
        'Enfermedad registrada',
        'La enfermedad catastrófica fue registrada correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
      handleClose()
    } catch (error) {
      notificar.error('No se pudo registrar la enfermedad', getApiErrorMessage(error))
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title="Registrar enfermedad catastrófica"
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Registrar enfermedad"
      submitting={isSubmitting}
    >
      <Stack gap="sm">
        <TextInput
          label="Nombre de la enfermedad"
          placeholder="Ej: Cáncer pulmonar, Diabetes tipo 1"
          {...contained}
          {...register('tipo_enfermedad')}
          error={errors.tipo_enfermedad?.message}
        />
        <TextInput
          label="Código CIE-10"
          placeholder="Ej: C34.1 (opcional)"
          {...contained}
          {...register('codigo_cie10')}
          error={errors.codigo_cie10?.message}
        />
        <Controller
          name="fecha_diagnostico"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de diagnóstico"
              placeholder="Seleccionar fecha (opcional)"
              valueFormat="YYYY-MM-DD"
              clearable
              maxDate={new Date()}
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d))}
              error={errors.fecha_diagnostico?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}

