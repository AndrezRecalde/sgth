'use client'

import { useEffect } from 'react'
import {
  Modal, Button, Group, Stack, TextInput, Textarea, Switch, Alert,
} from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePartidaPresupuestariaMutations } from '../hooks/usePartidaPresupuestariaMutations'
import {
  partidaPresupuestariaSchema,
  type PartidaPresupuestariaFormData,
} from '../schemas/partidaPresupuestaria.schema'
import type { PartidaPresupuestaria } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  partida?: PartidaPresupuestaria | null
}

const valoresDe = (partida?: PartidaPresupuestaria | null): PartidaPresupuestariaFormData => ({
  codigo: partida?.codigo ?? '',
  descripcion: partida?.descripcion ?? '',
  grupo_gasto: partida?.grupo_gasto ?? 'Gastos en Personal',
  activo: partida?.activo ?? true,
  disponible: partida?.disponible ?? false,
})

export function PartidaPresupuestariaModal({ opened, onClose, partida }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear, editar } = usePartidaPresupuestariaMutations()
  const isEditing = !!partida

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<PartidaPresupuestariaFormData>({
    resolver: zodResolver(partidaPresupuestariaSchema) as Resolver<PartidaPresupuestariaFormData>,
    defaultValues: valoresDe(partida),
  })

  useEffect(() => {
    reset(valoresDe(partida))
  }, [partida, reset])

  const handleClose = () => {
    reset(valoresDe(null))
    onClose()
  }

  const onSubmit = (values: PartidaPresupuestariaFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: partida!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(handleClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar partida presupuestaria' : 'Nueva partida presupuestaria'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Código"
            placeholder="Ej: 510105"
            {...contained}
            {...register('codigo')}
            error={errors.codigo?.message}
          />
          <Textarea
            label="Descripción"
            placeholder="Ej: Remuneraciones Unificadas"
            rows={2}
            {...contained}
            {...register('descripcion')}
            error={errors.descripcion?.message}
          />
          <TextInput
            label="Grupo de gasto"
            placeholder="Ej: Gastos en Personal"
            {...contained}
            {...register('grupo_gasto')}
            error={errors.grupo_gasto?.message}
          />

          <Controller
            name="activo"
            control={control}
            render={({ field }) => (
              <Switch
                label="Activa"
                description="Las partidas inactivas dejan de ofrecerse en los selectores, pero se conservan en el histórico."
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />

          <Controller
            name="disponible"
            control={control}
            render={({ field }) => (
              <Switch
                label="Disponibilidad presupuestaria verificada"
                description="Marque solo si el área presupuestaria confirmó que la partida tiene fondos."
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />

          <Alert
            variant="light"
            color="blue"
            icon={<IconInfoCircle size={16} />}
          >
            Las acciones de personal con efecto económico no pueden suscribirse
            si su partida no está marcada como disponible (Art. 105 LOSEP).
          </Alert>

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={isPending} color="emerald">
              {isEditing ? 'Actualizar' : 'Crear partida'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
