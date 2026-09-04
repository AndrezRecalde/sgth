'use client'

import {
  Modal, Stack, NumberInput, Textarea,
  Button, Group, Text, Badge, Alert,
} from '@mantine/core'
import { useEffect } from 'react'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { IconCheck, IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useInventarioMutations } from '../hooks/useInventarioMedicina'
import type { InventarioMedicina } from '../services/inventarioMedicinaService'

interface Props {
  opened:   boolean
  onClose:  () => void
  medicina: InventarioMedicina | null
}

type FormData = {
  nuevo_stock: number
  motivo:      string
}

export function AjustarInventarioModal({
  opened, onClose, medicina,
}: Props) {
  const contained = useContainedInput()
  const { ajustarInventario } = useInventarioMutations()

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: { nuevo_stock: 0, motivo: '' },
  })

  // El modal vive montado en la vista, así que los `defaultValues` se fijaron
  // cuando todavía no había medicina elegida y el conteo arrancaba en cero.
  // Se resiembra en cada apertura con el stock de la medicina en cuestión.
  useEffect(() => {
    if (opened && medicina) {
      reset({ nuevo_stock: medicina.stock_actual, motivo: '' })
    }
  }, [opened, medicina, reset])

  const nuevoStock = useWatch({ control, name: 'nuevo_stock' })

  const onSubmit = (values: FormData) => {
    if (!medicina) return
    ajustarInventario.mutateAsync({
      id: medicina.id,
      nuevoStock: values.nuevo_stock,
      motivo: values.motivo,
    }).then(() => {
      reset()
      onClose()
    }).catch(() => {})
  }

  if (!medicina) return null

  const diferencia = nuevoStock - medicina.stock_actual

  return (
    <Modal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title="Ajustar inventario"
      size="sm"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          <Group justify="space-between">
            <Text size="sm" fw={600}>{medicina.nombre}</Text>
            <Badge variant="light" color="blue">
              Stock en sistema: {medicina.stock_actual}
            </Badge>
          </Group>

          <Alert
            icon={<IconAlertTriangle size={14} />}
            color="orange"
            variant="light"
          >
            <Text size="xs">
              Use esta opción solo para corregir el inventario tras un conteo
              físico, una merma o una caducidad. Las compras y donaciones
              entran por Adquisiciones, con su documento de respaldo.
            </Text>
          </Alert>

          <Controller
            name="nuevo_stock"
            control={control}
            rules={{
              required: 'Indique el stock contado',
              min: { value: 0, message: 'No puede ser negativo' },
            }}
            render={({ field }) => (
              <NumberInput
                label="Stock real (conteo físico)"
                min={0}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 0)}
                error={errors.nuevo_stock?.message}
              />
            )}
          />

          {diferencia !== 0 && (
            <Text
              size="xs"
              c={diferencia > 0 ? 'emerald' : 'red'}
              fw={500}
            >
              {diferencia > 0
                ? `Se sumarán ${diferencia} unidades`
                : `Se restarán ${Math.abs(diferencia)} unidades`}
            </Text>
          )}

          <Textarea
            label="Motivo del ajuste"
            placeholder="Ej: Conteo físico mensual, rotura de envase, etc."
            autosize
            minRows={2}
            {...contained}
            required
            {...register('motivo', {
              required: 'Indique el motivo del ajuste',
            })}
            error={errors.motivo?.message}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="blue"
              leftSection={<IconCheck size={14} />}
              disabled={diferencia === 0}
              loading={ajustarInventario.isPending}
            >
              Confirmar ajuste
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
