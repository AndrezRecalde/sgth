'use client'

import { Stack, TextInput, Select, NumberInput, Group, Button, Modal } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { antecedenteSchema, type AntecedenteForm } from '../../schemas/femo.schema'
import { TIPO_ANTECEDENTE_OPTIONS } from '../../services/femoOptions'

interface Props {
  opened:  boolean
  onClose: () => void
  onAgregar: (values: AntecedenteForm) => void
}

export function FemoAntecedenteModal({ opened, onClose, onAgregar }: Props) {
  const contained = useContainedInput()

  const antForm = useForm<AntecedenteForm>({
    resolver: zodResolver(antecedenteSchema),
    defaultValues: { tipo: '', descripcion: '', fecha_aproximada: null },
  })

  const handleSubmit = (values: AntecedenteForm) => {
    onAgregar(values)
    antForm.reset()
    onClose()
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Agregar antecedente" size="sm" radius="xl">
      <form onSubmit={antForm.handleSubmit(handleSubmit)}>
        <Stack gap="sm">
          <Controller
            name="tipo"
            control={antForm.control}
            render={({ field }) => (
              <Select
                label="Tipo"
                data={TIPO_ANTECEDENTE_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={antForm.formState.errors.tipo?.message}
              />
            )}
          />
          <TextInput
            label="Descripción"
            required
            {...contained}
            {...antForm.register('descripcion')}
            error={antForm.formState.errors.descripcion?.message}
          />
          <Controller
            name="fecha_aproximada"
            control={antForm.control}
            render={({ field }) => (
              <NumberInput
                label="Año aproximado (opcional)"
                placeholder="Ej: 2018"
                min={1900}
                max={new Date().getFullYear()}
                {...contained}
                value={field.value ?? undefined}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />
          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" color="emerald" leftSection={<IconCheck size={14} />}>
              Agregar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
