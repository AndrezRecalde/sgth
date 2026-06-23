'use client'

import {
  Modal, Stack, NumberInput, Textarea,
  Button, Group, Text, Badge,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useInventarioMutations } from '../hooks/useInventarioMedicina'
import type { InventarioMedicina } from '../services/inventarioMedicinaService'

interface Props {
  opened:   boolean
  onClose:  () => void
  medicina: InventarioMedicina | null
}

type FormData = {
  cantidad: number
  motivo:   string
}

export function IngresarStockModal({
  opened, onClose, medicina,
}: Props) {
  const contained = useContainedInput()
  const { ingresarStock } = useInventarioMutations()

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: { cantidad: 0, motivo: '' },
  })

  const onSubmit = (values: FormData) => {
    if (!medicina) return
    ingresarStock.mutateAsync({
      id: medicina.id,
      cantidad: values.cantidad,
      motivo: values.motivo,
    }).then(() => {
      reset()
      onClose()
    }).catch(() => {})
  }

  if (!medicina) return null

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Ingresar stock"
      size="sm"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Group justify="space-between">
            <Text size="sm" fw={600}>{medicina.nombre}</Text>
            <Badge variant="light" color="blue">
              Stock actual: {medicina.stock_actual}
            </Badge>
          </Group>

          <Controller
            name="cantidad"
            control={control}
            rules={{ required: true, min: 1 }}
            render={({ field }) => (
              <NumberInput
                label="Cantidad a ingresar"
                min={1}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 0)}
                error={errors.cantidad?.message}
              />
            )}
          />

          <Textarea
            label="Motivo del ingreso"
            placeholder="Ej: Compra institucional, donación, etc."
            autosize
            minRows={2}
            {...contained}
            {...register('motivo', { required: true })}
            error={errors.motivo?.message}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={ingresarStock.isPending}
            >
              Ingresar stock
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
