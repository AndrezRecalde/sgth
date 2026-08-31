'use client'

import { useEffect } from 'react'
import {
  Modal, Button, Group, Stack,
  TextInput, Select, Switch, NumberInput,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useEquipoProteccionMutations } from '../hooks/useEquiposProteccion'
import {
  equipoProteccionSchema, type EquipoProteccionFormData, TIPO_EPP_OPTIONS,
} from '../schemas/equipoProteccion.schema'
import type { EquipoProteccion } from '../services/ssoService'

interface Props {
  opened: boolean
  onClose: () => void
  equipo?: EquipoProteccion | null
}

export function EquipoProteccionModal({ opened, onClose, equipo }: Props) {
  const { isMobile }      = useMobileBreakpoint()
  const contained         = useContainedInput()
  const { crear, editar } = useEquipoProteccionMutations()
  const isEditing         = !!equipo

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<EquipoProteccionFormData>({
    resolver: zodResolver(equipoProteccionSchema) as Resolver<EquipoProteccionFormData>,
    defaultValues: {
      codigo:           equipo?.codigo           ?? '',
      nombre:           equipo?.nombre           ?? '',
      tipo:             (equipo?.tipo as EquipoProteccionFormData['tipo']) ?? 'craneal',
      norma_tecnica:    equipo?.norma_tecnica     ?? '',
      vida_util_meses:  equipo?.vida_util_meses   ?? undefined,
      estado:           equipo?.estado            ?? true,
    },
  })

  useEffect(() => {
    reset({
      codigo:           equipo?.codigo           ?? '',
      nombre:           equipo?.nombre           ?? '',
      tipo:             (equipo?.tipo as EquipoProteccionFormData['tipo']) ?? 'craneal',
      norma_tecnica:    equipo?.norma_tecnica     ?? '',
      vida_util_meses:  equipo?.vida_util_meses   ?? undefined,
      estado:           equipo?.estado            ?? true,
    })
  }, [equipo, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: EquipoProteccionFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: equipo!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(handleClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar equipo de protección' : 'Nuevo equipo de protección'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form noValidate onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Código"
            placeholder="Ej: EPP-001"
            required
            {...contained}
            {...register('codigo')}
            error={errors.codigo?.message}
          />
          <TextInput
            label="Nombre"
            placeholder="Ej: Casco de seguridad"
            required
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo"
                data={TIPO_EPP_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v as EquipoProteccionFormData['tipo'])}
                error={errors.tipo?.message}
              />
            )}
          />
          <TextInput
            label="Norma técnica"
            placeholder="Ej: NTE INEN 2237 (opcional)"
            {...contained}
            {...register('norma_tecnica')}
            error={errors.norma_tecnica?.message}
          />
          <Controller
            name="vida_util_meses"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Vida útil (meses)"
                min={1}
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
              />
            )}
          />
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Switch
                label="Activo"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={isPending} color="emerald">
              {isEditing ? 'Actualizar' : 'Registrar equipo'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
