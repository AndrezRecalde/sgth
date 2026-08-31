'use client'

import { Modal, Stack, TextInput, Select, Button, Group } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePsicosocialMutations } from '../hooks/usePsicosocial'
import {
  crearCampaniaPsicosocialSchema, type CrearCampaniaPsicosocialFormData,
} from '../schemas/psicosocial.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'

interface Props {
  opened: boolean
  onClose: () => void
}

export function CrearCampaniaPsicosocialModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crearCampania } = usePsicosocialMutations()
  const { data: unidades = [] } = useTodasUnidades()

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<CrearCampaniaPsicosocialFormData>({
    resolver: zodResolver(crearCampaniaPsicosocialSchema) as Resolver<CrearCampaniaPsicosocialFormData>,
    defaultValues: { periodo: '', unidad_administrativa_id: null, fecha_apertura: '', fecha_cierre: null },
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: CrearCampaniaPsicosocialFormData) => {
    crearCampania.mutateAsync(values).then(handleClose).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Nueva campaña de evaluación psicosocial"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          <TextInput
            label="Período"
            placeholder="2026 o 2026-07"
            description="Formato AAAA (año) o AAAA-MM (mes)"
            required
            {...contained}
            {...register('periodo')}
            error={errors.periodo?.message}
          />
          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                description="Dejar vacío para aplicar a toda la institución"
                placeholder="Toda la institución"
                data={unidades.map(u => ({ value: String(u.id), label: u.nombre ?? '' }))}
                clearable
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />
          <Controller
            name="fecha_apertura"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de apertura"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                required
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                error={errors.fecha_apertura?.message}
              />
            )}
          />
          <Controller
            name="fecha_cierre"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de cierre (opcional)"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(d ? fromDateValue(d) : null)}
                error={errors.fecha_cierre?.message}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={crearCampania.isPending} color="emerald">
              Crear campaña
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
