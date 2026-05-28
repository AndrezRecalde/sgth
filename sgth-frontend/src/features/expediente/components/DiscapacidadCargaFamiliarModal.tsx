'use client'

import { Modal, Button, Group, Stack,
         Select, NumberInput, TextInput } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import { expedienteService } from '../services/expedienteService'
import React from 'react'

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
  const { isMobile } = useMobileBreakpoint()
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
      await expedienteService.crearDiscapacidadCarga(
        cargaId,
        values as Record<string, unknown>
      )
      notifications.show({
        title:   'Discapacidad registrada',
        message: 'La discapacidad fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
      handleClose()
    } catch {
      notifications.show({
        title:   'Error',
        message: 'No se pudo registrar la discapacidad.',
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      })
    }
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Registrar discapacidad"
      size="sm"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
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
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="orange"
              variant="light"
              loading={isSubmitting}
            >
              Registrar discapacidad
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
