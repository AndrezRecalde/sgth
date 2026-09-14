'use client'

import React, { useEffect } from 'react'
import { Stack, TextInput } from '@mantine/core'
import { FormModal, notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { expedienteService } from '../services/expedienteService'
import { useQueryClient } from '@tanstack/react-query'
import { enfermedadSchema, type EnfermedadFormData }
  from '../schemas/enfermedad.schema'
import { DatePickerInput } from '@mantine/dates'

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
  initialValues?: {
    id:                  number
    tipo_enfermedad:     string
    codigo_cie10?:       string | null
    fecha_diagnostico?:  string | null
  } | null
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const datePart = v.split('T')[0]
  const [year, month, day] = datePart.split('-').map(Number)
  return new Date(year, month - 1, day)
}
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function EnfermedadModal({ opened, onClose, servidorId, initialValues }: Props) {
  const contained = useContainedInput()
  const qc = useQueryClient()

  const { register, handleSubmit, reset, control, formState: { errors, isSubmitting } } =
    useForm<EnfermedadFormData>({
      resolver: zodResolver(enfermedadSchema),
      defaultValues: {
        tipo_enfermedad:    '',
        codigo_cie10:       '',
        fecha_diagnostico:  '',
      },
    })

  useEffect(() => {
    if (initialValues) {
      reset({
        tipo_enfermedad:   initialValues.tipo_enfermedad,
        codigo_cie10:      initialValues.codigo_cie10 ?? '',
        fecha_diagnostico: initialValues.fecha_diagnostico
          ? initialValues.fecha_diagnostico.split('T')[0] : '',
      })
    } else {
      reset({
        tipo_enfermedad:   '',
        codigo_cie10:      '',
        fecha_diagnostico: '',
      })
    }
  }, [initialValues, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const isEditing = !!initialValues

  const onSubmit = async (values: EnfermedadFormData) => {
    const payload = {
      ...values,
      codigo_cie10: values.codigo_cie10 || null,
      fecha_diagnostico: values.fecha_diagnostico || null,
    }
    try {
      if (isEditing) {
        await expedienteService.editarEnfermedad(
          servidorId, initialValues!.id,
          payload
        )
      } else {
        await expedienteService.crearEnfermedad(
          servidorId, payload
        )
      }
      qc.invalidateQueries({ queryKey: ['enfermedades', servidorId] })
      notificar.exito(
        isEditing ? 'Enfermedad actualizada' : 'Enfermedad registrada',
        'El registro fue procesado correctamente.',
      )
      handleClose()
    } catch (error) {
      notificar.error(
        isEditing ? 'No se pudo actualizar la enfermedad' : 'No se pudo registrar la enfermedad',
        getApiErrorMessage(error),
      )
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? 'Editar enfermedad catastrófica' : 'Registrar enfermedad catastrófica'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={initialValues ? 'Actualizar' : 'Registrar enfermedad'}
      submitting={isSubmitting}
    >
      <Stack gap="sm">
        <TextInput label="Nombre/Tipo de la enfermedad"
          placeholder="Diagnóstico médico"
          {...contained} {...register('tipo_enfermedad')}
          error={errors.tipo_enfermedad?.message} />
        <TextInput label="Código CIE-10 (Opcional)"
          placeholder="Ej: C18.0"
          {...contained} {...register('codigo_cie10')}
          error={errors.codigo_cie10?.message} />
        <Controller
          name="fecha_diagnostico"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de diagnóstico"
              placeholder="Seleccionar fecha"
              valueFormat="YYYY-MM-DD"
              clearable
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

