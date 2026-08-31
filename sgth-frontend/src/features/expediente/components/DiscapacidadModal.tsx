'use client'

import React, { useEffect } from 'react'
import { Modal, Button, Group, Stack, TextInput,
         NumberInput, Select } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { expedienteService } from '../services/expedienteService'
import { useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import { discapacidadSchema, type DiscapacidadFormData }
  from '../schemas/discapacidad.schema'

const TIPO_OPTIONS = [
  { value: 'fisica',       label: 'Física' },
  { value: 'sensorial',    label: 'Sensorial (Visual / Auditiva)' },
  { value: 'intelectual',  label: 'Intelectual' },
  { value: 'psicosocial',  label: 'Psicosocial o Mental' },
  { value: 'visceral',     label: 'Visceral u Orgánica' },
  { value: 'multiple',     label: 'Múltiple' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
  initialValues?: {
    id:                    number
    tipo_discapacidad:     string
    porcentaje:            string | number
    numero_carnet_conadis?: string | null
  } | null
}

export function DiscapacidadModal({ opened, onClose, servidorId, initialValues }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const qc = useQueryClient()

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } =
    useForm<DiscapacidadFormData>({
      resolver: zodResolver(discapacidadSchema),
      defaultValues: {
        tipo_discapacidad:     'fisica',
        porcentaje:            1,
        numero_carnet_conadis: '',
      },
    })

  useEffect(() => {
    if (initialValues) {
      reset({
        tipo_discapacidad: initialValues.tipo_discapacidad as DiscapacidadFormData['tipo_discapacidad'],
        porcentaje:        Number(initialValues.porcentaje),
        numero_carnet_conadis: initialValues.numero_carnet_conadis ?? '',
      })
    } else {
      reset({
        tipo_discapacidad:     'fisica',
        porcentaje:            1,
        numero_carnet_conadis: '',
      })
    }
  }, [initialValues, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const isEditing = !!initialValues

  const onSubmit = async (values: DiscapacidadFormData) => {
    try {
      if (isEditing) {
        await expedienteService.editarDiscapacidad(
          servidorId, initialValues!.id,
          values
        )
      } else {
        await expedienteService.crearDiscapacidad(
          servidorId, values
        )
      }
      qc.invalidateQueries({ queryKey: ['discapacidades', servidorId] })
      notifications.show({
        title:   isEditing ? 'Discapacidad actualizada' : 'Discapacidad registrada',
        message: isEditing
          ? 'El registro fue actualizado correctamente.'
          : 'La discapacidad fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      handleClose()
    } catch {
      notifications.show({
        title:   'Error',
        message: 'No se pudo procesar el registro.',
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      })
    }
  }

  return (
    <Modal opened={opened} onClose={handleClose}
      title={initialValues ? 'Editar discapacidad' : 'Registrar discapacidad'}
      size="sm" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form noValidate onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller name="tipo_discapacidad" control={control}
            render={({ field }) => (
              <Select label="Tipo de discapacidad"
                data={TIPO_OPTIONS} {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.tipo_discapacidad?.message} />
            )} />
          <Controller name="porcentaje" control={control}
            render={({ field }) => (
              <NumberInput label="Porcentaje de discapacidad"
                placeholder="%" min={1} max={100} suffix="%"
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                error={errors.porcentaje?.message} />
            )} />
          <TextInput label="Número de carnet CONADIS"
            placeholder="Ingrese el número de carnet"
            {...contained} {...register('numero_carnet_conadis')}
            error={errors.numero_carnet_conadis?.message} />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={isSubmitting}>
              {initialValues ? 'Actualizar' : 'Registrar discapacidad'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}

