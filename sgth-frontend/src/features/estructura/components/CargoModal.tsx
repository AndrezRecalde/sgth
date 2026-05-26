'use client'

import { Modal, Button, Group, Stack, TextInput, Select, Textarea } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
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
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { crear, editar } = useCargoMutations()
  const isEditing = !!cargo

  const form = useForm<CargoFormData>({
    initialValues: {
      nombre:                cargo?.nombre                ?? '',
      denominacion_generica: cargo?.denominacion_generica ?? '',
      mision:                cargo?.mision                ?? '',
      clasificacion_personal: (cargo?.clasificacion_personal as CargoFormData['clasificacion_personal'])
        ?? 'empleado',
    },
    validate: zodResolver(cargoSchema),
  })

  const handleSubmit = (values: CargoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: cargo!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(() => { form.reset(); onClose() }).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar cargo' : 'Nuevo cargo'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Nombre del cargo"
            placeholder="Ej: Analista de Seguridad Informática"
            {...contained}
            {...form.getInputProps('nombre')}
          />
          <TextInput
            label="Denominación genérica"
            placeholder="Ej: Analista"
            {...contained}
            {...form.getInputProps('denominacion_generica')}
          />
          <Select
            label="Clasificación de personal"
            data={CLASIFICACION_OPTIONS}
            {...contained}
            value={form.values.clasificacion_personal}
            onChange={(v) =>
              form.setFieldValue('clasificacion_personal',
                (v ?? 'empleado') as CargoFormData['clasificacion_personal'])
            }
            error={form.errors.clasificacion_personal}
          />
          <Textarea
            label="Misión del cargo"
            placeholder="Describa la misión del cargo (opcional)"
            rows={3}
            {...contained}
            {...form.getInputProps('mision')}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>
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
