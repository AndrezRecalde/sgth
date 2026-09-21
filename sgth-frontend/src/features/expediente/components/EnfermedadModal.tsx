'use client'

import React, { useEffect } from 'react'
import { Stack, TextInput } from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCondicionMutations } from '../hooks/useCondicionMutations'
import { enfermedadSchema, type EnfermedadFormData }
  from '../schemas/enfermedad.schema'
import { DatePickerInput } from '@mantine/dates'
import { toDateValue, fromDateValueOrNull } from '@/lib/fecha'

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

export function EnfermedadModal({ opened, onClose, servidorId, initialValues }: Props) {
  const contained = useContainedInput()
  const { crearEnfermedad, editarEnfermedad } = useCondicionMutations(servidorId)

  const { register, handleSubmit, reset, control, formState: { errors } } =
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


  const onSubmit = async (values: EnfermedadFormData) => {
    const payload = {
      ...values,
      codigo_cie10: values.codigo_cie10 || null,
      fecha_diagnostico: values.fecha_diagnostico || null,
    }
    const guardado = initialValues
      ? editarEnfermedad.mutateAsync({ id: initialValues.id, data: payload })
      : crearEnfermedad.mutateAsync(payload)
    guardado.then(handleClose).catch(() => {}) // el hook ya notificó
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? 'Editar enfermedad catastrófica' : 'Registrar enfermedad catastrófica'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={initialValues ? 'Actualizar' : 'Registrar enfermedad'}
      submitting={crearEnfermedad.isPending || editarEnfermedad.isPending}
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
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_diagnostico?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}

