'use client'

import { useState } from 'react'
import {
  Stack, Group, Button, Text, Card,
  TextInput, Textarea, NumberInput,
  ActionIcon, Divider, Badge, Grid,
  Alert,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import {
  IconPlus, IconTrash, IconInfoCircle,
} from '@tabler/icons-react'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import {
  liquidacionSchema,
  type LiquidacionFormData,
} from '../schemas/viatico.schema'
import type { Viatico } from '@/types/api'

interface Props {
  viatico:   Viatico
  onSuccess: () => void
}

const CONCEPTO_OPTIONS = [
  'alimentacion',
  'hospedaje',
  'transporte_terrestre',
  'pasaje_aereo',
  'combustible',
  'peaje',
  'materiales',
  'otro',
]

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const fromDate = (d: Date | null): string | null => {
  if (!d) return null
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

export function LiquidacionForm({ viatico, onSuccess }: Props) {
  const contained = useContainedInput()
  const { liquidar } = useViaticoMutations()

  const {
    control,
    handleSubmit,
    register,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<LiquidacionFormData>({
    resolver: zodResolver(liquidacionSchema),
    defaultValues: {
      fecha_retorno:  '',
      observaciones:  '',
      facturas: [{
        concepto:         'alimentacion',
        detalle:          '',
        numero_factura:   '',
        ruc_proveedor:    '',
        nombre_proveedor: '',
        monto:            0,
      }],
      actividades: [{
        fecha:       '',
        hora_inicio: '08:00',
        hora_fin:    '17:00',
        descripcion: '',
        lugar:       '',
      }],
    },
  })

  const {
    fields: facturaFields,
    append: appendFactura,
    remove: removeFactura,
  } = useFieldArray({ control, name: 'facturas' })

  const {
    fields: actividadFields,
    append: appendActividad,
    remove: removeActividad,
  } = useFieldArray({ control, name: 'actividades' })

  const facturasWatch = watch('facturas')
  const totalFacturas = facturasWatch.reduce(
    (sum, f) => sum + (Number(f.monto) || 0), 0
  )
  const anticipo    = Number(viatico.monto_anticipo ?? 0)
  const diferencia  = anticipo - totalFacturas

  const onSubmit = async (values: LiquidacionFormData) => {
    await liquidar.mutateAsync({
      viaticoId: viatico.id,
      data:      values,
    })
    onSuccess()
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="md">

        {/* Resumen financiero */}
        <Card withBorder radius="md" p="sm" bg="gray.0">
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Anticipo recibido:</Text>
            <Text size="sm" fw={600}>
              ${anticipo.toFixed(2)}
            </Text>
          </Group>
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Total facturas:</Text>
            <Text size="sm" fw={600} c="blue">
              ${totalFacturas.toFixed(2)}
            </Text>
          </Group>
          <Divider my={4} />
          <Group justify="space-between">
            <Text size="sm" fw={600}>
              {diferencia >= 0 ? 'A devolver:' : 'Diferencia a cobrar:'}
            </Text>
            <Text
              size="sm"
              fw={700}
              c={diferencia >= 0 ? 'orange' : 'emerald'}
            >
              ${Math.abs(diferencia).toFixed(2)}
            </Text>
          </Group>
        </Card>

        {/* Fecha de retorno */}
        <Controller
          name="fecha_retorno"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de retorno"
              placeholder="Seleccionar fecha"
              valueFormat="YYYY-MM-DD"
              {...contained}
              value={toDate(field.value)}
              onChange={(v) => field.onChange(fromDate(v as any) ?? '')}
              error={errors.fecha_retorno?.message}
            />
          )}
        />

        {/* Actividades */}
        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600}>
                Informe de actividades
              </Text>
              <Badge size="xs" color="blue">
                {actividadFields.length}
              </Badge>
            </Group>
          }
          labelPosition="left"
        />

        <Stack gap="xs">
          {actividadFields.map((field, i) => (
            <Card key={field.id} withBorder radius="md" p="sm">
              <Group justify="space-between" mb="xs">
                <Text size="xs" fw={600} c="dimmed">
                  Actividad {i + 1}
                </Text>
                {actividadFields.length > 1 && (
                  <ActionIcon
                    size="xs"
                    color="red"
                    variant="subtle"
                    onClick={() => removeActividad(i)}
                  >
                    <IconTrash size={12} />
                  </ActionIcon>
                )}
              </Group>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name={`actividades.${i}.fecha`}
                    control={control}
                    render={({ field: f }) => (
                      <DatePickerInput
                        label="Fecha"
                        placeholder="Seleccionar"
                        valueFormat="YYYY-MM-DD"
                        {...contained}
                        value={toDate(f.value)}
                        onChange={(v) =>
                          f.onChange(fromDate(v as any) ?? '')
                        }
                        error={
                          errors.actividades?.[i]?.fecha?.message
                        }
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 4 }}>
                  <TextInput
                    label="Hora inicio"
                    type="time"
                    {...contained}
                    {...register(`actividades.${i}.hora_inicio`)}
                    error={
                      errors.actividades?.[i]?.hora_inicio?.message
                    }
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 4 }}>
                  <TextInput
                    label="Hora fin"
                    type="time"
                    {...contained}
                    {...register(`actividades.${i}.hora_fin`)}
                    error={
                      errors.actividades?.[i]?.hora_fin?.message
                    }
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 8 }}>
                  <TextInput
                    label="Lugar"
                    placeholder="Ciudad / Institución"
                    {...contained}
                    {...register(`actividades.${i}.lugar`)}
                    error={
                      errors.actividades?.[i]?.lugar?.message
                    }
                  />
                </Grid.Col>
              </Grid>
              <Textarea
                label="Descripción de la actividad"
                placeholder="Detalle las actividades realizadas"
                autosize
                minRows={2}
                maxRows={4}
                mt="xs"
                {...contained}
                {...register(`actividades.${i}.descripcion`)}
                error={
                  errors.actividades?.[i]?.descripcion?.message
                }
              />
            </Card>
          ))}

          <Button
            size="xs"
            variant="light"
            color="blue"
            leftSection={<IconPlus size={12} />}
            onClick={() => appendActividad({
              fecha: '', hora_inicio: '08:00',
              hora_fin: '17:00', descripcion: '', lugar: '',
            })}
          >
            Agregar actividad
          </Button>
        </Stack>

        {/* Facturas */}
        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600}>Facturas de respaldo</Text>
              <Badge size="xs" color="orange">
                {facturaFields.length}
              </Badge>
            </Group>
          }
          labelPosition="left"
        />

        <Stack gap="xs">
          {facturaFields.map((field, i) => (
            <Card key={field.id} withBorder radius="md" p="sm">
              <Group justify="space-between" mb="xs">
                <Text size="xs" fw={600} c="dimmed">
                  Factura {i + 1}
                </Text>
                {facturaFields.length > 1 && (
                  <ActionIcon
                    size="xs"
                    color="red"
                    variant="subtle"
                    onClick={() => removeFactura(i)}
                  >
                    <IconTrash size={12} />
                  </ActionIcon>
                )}
              </Group>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name={`facturas.${i}.concepto`}
                    control={control}
                    render={({ field: f }) => (
                      <TextInput
                        label="Concepto"
                        placeholder="alimentacion, hospedaje..."
                        {...contained}
                        value={f.value}
                        onChange={(e) =>
                          f.onChange(e.currentTarget.value)
                        }
                        error={
                          errors.facturas?.[i]?.concepto?.message
                        }
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <TextInput
                    label="N° Factura"
                    placeholder="001-001-000000001"
                    {...contained}
                    {...register(`facturas.${i}.numero_factura`)}
                    error={
                      errors.facturas?.[i]?.numero_factura?.message
                    }
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <TextInput
                    label="RUC proveedor"
                    placeholder="0000000000001"
                    {...contained}
                    {...register(`facturas.${i}.ruc_proveedor`)}
                    error={
                      errors.facturas?.[i]?.ruc_proveedor?.message
                    }
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <TextInput
                    label="Proveedor"
                    placeholder="Nombre del proveedor"
                    {...contained}
                    {...register(`facturas.${i}.nombre_proveedor`)}
                    error={
                      errors.facturas?.[i]
                        ?.nombre_proveedor?.message
                    }
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name={`facturas.${i}.monto`}
                    control={control}
                    render={({ field: f }) => (
                      <NumberInput
                        label="Monto"
                        prefix="$"
                        decimalScale={2}
                        min={0}
                        {...contained}
                        value={f.value}
                        onChange={(v) =>
                          f.onChange(
                            typeof v === 'number' ? v : 0
                          )
                        }
                        error={
                          errors.facturas?.[i]?.monto?.message
                        }
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <TextInput
                    label="Detalle (opcional)"
                    placeholder="Descripción adicional"
                    {...contained}
                    {...register(`facturas.${i}.detalle`)}
                  />
                </Grid.Col>
              </Grid>
            </Card>
          ))}

          <Button
            size="xs"
            variant="light"
            color="orange"
            leftSection={<IconPlus size={12} />}
            onClick={() => appendFactura({
              concepto: 'alimentacion', detalle: '',
              numero_factura: '', ruc_proveedor: '',
              nombre_proveedor: '', monto: 0,
            })}
          >
            Agregar factura
          </Button>
        </Stack>

        <Controller
          name="observaciones"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Observaciones (opcional)"
              placeholder="Observaciones generales de la comisión"
              autosize
              minRows={2}
              {...contained}
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />

        <Group justify="flex-end" mt="md">
          <Button
            type="submit"
            color="emerald"
            loading={isSubmitting}
          >
            Registrar liquidación
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
