'use client'

import {
  Card, Grid, Select, TextInput,
  NumberInput, ActionIcon, Group,
  Text, Alert,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconTrash, IconInfoCircle } from '@tabler/icons-react'
import {
  Controller, useWatch,
  type Control, type UseFormRegister,
  type FieldErrors,
} from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { CategoriaFactura } from '@/types/api'

type FacturaFormData = {
  facturas: {
    categoria_factura_id: number
    fecha_factura?:       string | null
    tipo_comprobante:     'factura' | 'ticket' | 'recibo' | 'otro'
    numero_factura?:      string | null
    numero_ticket?:       string | null
    ruc_proveedor?:       string | null
    nombre_proveedor:     string
    detalle?:             string | null
    monto:                number
  }[]
}

type Opcion = { value: string; label: string }
type GrupoOpcion = { group: string; items: Opcion[] }

interface Props {
  index:             number
  control:           Control<FacturaFormData>
  register:          UseFormRegister<FacturaFormData>
  errors:            FieldErrors<FacturaFormData>
  categoriaOptions:  (Opcion | GrupoOpcion)[]
  minFecha?:         Date
  maxFecha?:         Date
  onEliminar:        () => void
  puedeEliminar:     boolean
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

const safeFormatDate = (v: Date | string | null | undefined): string => {
  if (!v) return ''
  const d = new Date(v)
  if (isNaN(d.getTime())) return ''
  if (d.getUTCHours() === 0 && d.getUTCMinutes() === 0) {
    return d.toISOString().slice(0, 10)
  }
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export function FacturaItemForm({
  index, control, register, errors,
  categoriaOptions, minFecha, maxFecha,
  onEliminar, puedeEliminar,
}: Props) {
  const contained = useContainedInput()

  const tipoComp = useWatch({
    control,
    name: `facturas.${index}.tipo_comprobante`,
  })

  const errFactura = errors.facturas?.[index]

  return (
    <Card withBorder radius="md" p="sm">
      <Group justify="space-between" mb="xs">
        <Text size="xs" fw={600} c="dimmed">
          Comprobante #{index + 1}
        </Text>
        {puedeEliminar && (
          <ActionIcon
            size="sm"
            color="red"
            variant="subtle"
            onClick={onEliminar}
          >
            <IconTrash size={14} />
          </ActionIcon>
        )}
      </Group>

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name={`facturas.${index}.categoria_factura_id`}
            control={control}
            render={({ field }) => (
              <Select
                label="Categoría"
                data={categoriaOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : 0)}
                error={errFactura?.categoria_factura_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name={`facturas.${index}.tipo_comprobante`}
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de comprobante"
                data={[
                  { value: 'factura', label: 'Factura' },
                  { value: 'ticket',  label: 'Ticket' },
                  { value: 'recibo',  label: 'Recibo' },
                  { value: 'otro',    label: 'Otro' },
                ]}
                {...contained}
                value={field.value}
                onChange={(v) =>
                  field.onChange(v ?? 'factura')
                }
                error={errFactura?.tipo_comprobante?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label={
              ['factura', 'recibo'].includes(tipoComp)
                ? 'RUC del proveedor *'
                : 'RUC / Identificación (opcional)'
            }
            placeholder="0000000000001"
            required={['factura', 'recibo'].includes(tipoComp)}
            {...contained}
            {...register(`facturas.${index}.ruc_proveedor`)}
            error={errFactura?.ruc_proveedor?.message}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Nombre del proveedor"
            placeholder="Ej: Hotel Quito"
            {...contained}
            {...register(`facturas.${index}.nombre_proveedor`)}
            error={errFactura?.nombre_proveedor?.message}
          />
        </Grid.Col>

        {tipoComp === 'ticket' ? (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Número de ticket"
              placeholder="T-001"
              {...contained}
              {...register(`facturas.${index}.numero_ticket`)}
              error={errFactura?.numero_ticket?.message}
            />
          </Grid.Col>
        ) : (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Número de comprobante"
              placeholder="001-001-000001"
              {...contained}
              {...register(`facturas.${index}.numero_factura`)}
              error={errFactura?.numero_factura?.message}
            />
          </Grid.Col>
        )}

        <Grid.Col span={{ base: 12, sm: 3 }}>
          <Controller
            name={`facturas.${index}.fecha_factura`}
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                valueFormat="DD/MM/YYYY"
                {...contained}
                minDate={minFecha}
                maxDate={maxFecha}
                value={toDate(field.value)}
                onChange={(v) =>
                  field.onChange(v ? safeFormatDate(v) : null)
                }
                error={errFactura?.fecha_factura?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 3 }}>
          <Controller
            name={`facturas.${index}.monto`}
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Monto"
                prefix="$"
                decimalScale={2}
                min={0.01}
                {...contained}
                value={field.value}
                onChange={(v) =>
                  field.onChange(typeof v === 'number' ? v : 0)
                }
                error={errFactura?.monto?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <TextInput
            label="Detalle (opcional)"
            placeholder="Ej: Noche del 08/06/2026"
            {...contained}
            {...register(`facturas.${index}.detalle`)}
          />
        </Grid.Col>
      </Grid>
    </Card>
  )
}
