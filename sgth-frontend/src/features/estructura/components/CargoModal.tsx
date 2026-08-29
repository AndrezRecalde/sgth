'use client'

import { useEffect } from 'react'
import {
  Modal, Button, Group, Stack,
  TextInput, Textarea, Anchor,
} from '@mantine/core'
import { useForm, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCargoMutations } from '../hooks/useCargoMutations'
import { cargoSchema, type CargoFormData } from '../schemas/cargo.schema'
import type { Cargo } from '@/types/api'

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
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CargoFormData>({
    resolver: zodResolver(cargoSchema) as Resolver<CargoFormData>,
    defaultValues: {
      nombre:                 cargo?.nombre                ?? '',
      denominacion_generica:  cargo?.denominacion_generica ?? '',
      codigo_ciuo:            cargo?.codigo_ciuo            ?? '',
      mision:                 cargo?.mision                ?? '',
    },
  })

  useEffect(() => {
    reset({
      nombre:                cargo?.nombre                ?? '',
      denominacion_generica: cargo?.denominacion_generica ?? '',
      codigo_ciuo:           cargo?.codigo_ciuo            ?? '',
      mision:                cargo?.mision                ?? '',
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
      <form onSubmit={handleSubmit(onSubmit)}>
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
          <TextInput
            label="Código CIUO"
            placeholder="Ej: 2519"
            description={
              <>
                Clasificación Internacional Uniforme de Ocupaciones. Lo heredan
                las fichas médicas ocupacionales de este cargo.{' '}
                <Anchor
                  href="https://aplicaciones2.ecuadorencifras.gob.ec/SIN/ciuo08.php"
                  target="_blank"
                  rel="noopener noreferrer"
                  size="xs"
                >
                  Buscar en el INEC
                </Anchor>
              </>
            }
            {...contained}
            {...register('codigo_ciuo')}
            error={errors.codigo_ciuo?.message}
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
