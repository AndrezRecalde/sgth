'use client'

import { useEffect } from 'react'
import {
  Modal, Button, Group, Stack,
  TextInput, Select, Textarea,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCargoMutations } from '../hooks/useCargoMutations'
import { cargoSchema, type CargoFormData } from '../schemas/cargo.schema'
import type { Cargo } from '@/types/api'

const CLASIFICACION_OPTIONS = [
  { value: 'empleado',   label: 'Empleado (LOSEP)' },
  { value: 'contratado', label: 'Contratado (Servicios Ocasionales/Profesionales)' },
  { value: 'obrero',     label: 'Obrero (Código del Trabajo)' },
]

interface Props {
  opened:  boolean
  onClose: () => void
  cargo?:  Cargo | null
}

export function CargoModal({ opened, onClose, cargo }: Props) {
  const { isMobile }      = useMobileBreakpoint()
  const contained         = useContainedInput()
  const { crear, editar } = useCargoMutations()
  const isEditing         = !!cargo

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CargoFormData>({
    resolver: zodResolver(cargoSchema) as Resolver<CargoFormData>,
    defaultValues: {
      nombre:                 cargo?.nombre                ?? '',
      denominacion_generica:  cargo?.denominacion_generica ?? '',
      mision:                 cargo?.mision                ?? '',
      clasificacion_personal: (cargo?.clasificacion_personal as CargoFormData['clasificacion_personal']) ?? 'empleado',
    },
  })

  useEffect(() => {
    reset({
      nombre:                cargo?.nombre                ?? '',
      denominacion_generica: cargo?.denominacion_generica ?? '',
      mision:                cargo?.mision                ?? '',
      clasificacion_personal: (cargo?.clasificacion_personal as CargoFormData['clasificacion_personal']) ?? 'empleado',
    })
  }, [cargo, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: CargoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: cargo!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(handleClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar cargo' : 'Nuevo cargo'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form noValidate onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Nombre del cargo"
            placeholder="Ej: Analista de Seguridad Informática"
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />
          <TextInput
            label="Denominación genérica"
            placeholder="Ej: Analista"
            {...contained}
            {...register('denominacion_generica')}
            error={errors.denominacion_generica?.message}
          />
          <Controller
            name="clasificacion_personal"
            control={control}
            render={({ field }) => (
              <Select
                label="Clasificación de personal"
                data={CLASIFICACION_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) =>
                  field.onChange((v ?? 'empleado') as CargoFormData['clasificacion_personal'])
                }
                error={errors.clasificacion_personal?.message}
              />
            )}
          />
          <Textarea
            label="Misión del cargo"
            placeholder="Describa la misión del cargo (opcional)"
            rows={3}
            {...contained}
            {...register('mision')}
            error={errors.mision?.message}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={isPending} color="emerald">
              {isEditing ? 'Actualizar' : 'Crear cargo'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
