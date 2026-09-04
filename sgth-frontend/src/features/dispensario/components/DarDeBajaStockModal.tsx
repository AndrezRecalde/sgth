'use client'

import {
  Modal, Stack, NumberInput, Textarea, Select,
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
  cantidad: number
  causa:    string
  detalle:  string
}

const CAUSAS = [
  { value: 'Caducidad',     label: 'Caducidad'                  },
  { value: 'Merma',         label: 'Merma'                      },
  { value: 'Rotura',        label: 'Rotura o envase dañado'     },
  { value: 'Contaminación', label: 'Contaminación'              },
  { value: 'Otra',          label: 'Otra'                       },
]

/** ¿Caducó ya? El día impreso en el envase todavía es válido. */
function estaCaducado(medicina: InventarioMedicina): boolean {
  if (!medicina.fecha_caducidad) return false
  const [y, m, d] = medicina.fecha_caducidad.slice(0, 10).split('-').map(Number)
  const caduca = new Date(y, m - 1, d)
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  return caduca < hoy
}

export function DarDeBajaStockModal({ opened, onClose, medicina }: Props) {
  const contained = useContainedInput()
  const { registrarBaja } = useInventarioMutations()

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: { cantidad: 0, causa: '', detalle: '' },
  })

  // Lo caducado se da de baja entero, que es el caso que trae aquí a casi
  // todo el mundo: se propone el stock completo y se puede corregir.
  useEffect(() => {
    if (opened && medicina) {
      reset({
        cantidad: estaCaducado(medicina) ? medicina.stock_actual : 0,
        causa:    estaCaducado(medicina) ? 'Caducidad' : '',
        detalle:  '',
      })
    }
  }, [opened, medicina, reset])

  const cantidad = useWatch({ control, name: 'cantidad' })

  const onSubmit = (values: FormData) => {
    if (!medicina) return
    const motivo = values.detalle.trim()
      ? `${values.causa} — ${values.detalle.trim()}`
      : values.causa

    registrarBaja.mutateAsync({
      id: medicina.id,
      cantidad: values.cantidad,
      motivo,
    }).then(() => {
      reset()
      onClose()
    }).catch(() => {})
  }

  if (!medicina) return null

  const caducado = estaCaducado(medicina)

  return (
    <Modal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title="Dar de baja existencias"
      size="sm"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          <Group justify="space-between">
            <Text size="sm" fw={600}>{medicina.nombre}</Text>
            <Badge variant="light" color="blue">
              Stock: {medicina.stock_actual}
            </Badge>
          </Group>

          <Alert
            icon={<IconAlertTriangle size={14} />}
            color={caducado ? 'red' : 'orange'}
            variant="light"
          >
            <Text size="xs">
              {caducado
                ? 'Estas existencias están caducadas y el despacho las rechaza. Al darlas de baja salen del inventario y queda constancia en el kardex.'
                : 'Las unidades salen del inventario por una causa conocida y queda constancia en el kardex. Para corregir una diferencia de conteo use «Ajustar inventario».'}
            </Text>
          </Alert>

          <Controller
            name="cantidad"
            control={control}
            rules={{
              required: 'Indique cuántas unidades salen',
              min: { value: 1, message: 'Debe ser al menos 1' },
              max: {
                value: medicina.stock_actual,
                message: `Solo quedan ${medicina.stock_actual}`,
              },
            }}
            render={({ field }) => (
              <NumberInput
                label="Unidades a dar de baja"
                min={1}
                max={medicina.stock_actual}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 0)}
                error={errors.cantidad?.message}
              />
            )}
          />

          {cantidad > 0 && cantidad <= medicina.stock_actual && (
            <Text size="xs" c="dimmed">
              El stock quedará en {medicina.stock_actual - cantidad}.
            </Text>
          )}

          <Controller
            name="causa"
            control={control}
            rules={{ required: 'Seleccione la causa' }}
            render={({ field }) => (
              <Select
                label="Causa"
                placeholder="Seleccione"
                data={CAUSAS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.causa?.message}
              />
            )}
          />

          <Textarea
            label="Detalle (opcional)"
            placeholder="Ej: lote L123 vencido el 12/08/2026"
            autosize
            minRows={2}
            {...contained}
            {...register('detalle')}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="orange"
              leftSection={<IconCheck size={14} />}
              loading={registrarBaja.isPending}
            >
              Dar de baja
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
