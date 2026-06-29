'use client'

import {
  Card, Stack, Group, Select, TextInput,
  Textarea, Button, Table, Text, Alert,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useDisclosure } from '@mantine/hooks'
import {
  IconCheck, IconPlus, IconInfoCircle,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRegistrarAdquisicion } from '../hooks/useAdquisicion'
import { BuscarMedicinaSelect } from './BuscarMedicinaSelect'
import { ItemAdquisicionRow } from './ItemAdquisicionRow'
import { MedicinaModal } from './MedicinaModal'
import {
  adquisicionSchema, type AdquisicionFormData,
} from '../schemas/adquisicion.schema'
import type { Adquisicion } from '../services/adquisicionService'

interface Props {
  onCreada: (adquisicion: Adquisicion) => void
}

const TIPO_OPTIONS = [
  { value: 'compra',   label: 'Compra'   },
  { value: 'donacion', label: 'Donación' },
]

function toDate(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}

export function AdquisicionForm({ onCreada }: Props) {
  const contained  = useContainedInput()
  const registrar  = useRegistrarAdquisicion()
  const [medicinaModalOpened,
    { open: abrirMedicinaModal, close: cerrarMedicinaModal }] =
    useDisclosure(false)

  const {
    control, register, handleSubmit,
    formState: { errors },
  } = useForm<AdquisicionFormData>({
    resolver: zodResolver(adquisicionSchema),
    defaultValues: {
      tipo:                'compra',
      numero_documento:    '',
      proveedor_o_donante: '',
      fecha_adquisicion:   new Date().toISOString().slice(0, 10),
      observaciones:       '',
      items:               [],
    },
  })

  const { fields, append, remove } = useFieldArray({
    control, name: 'items',
  })

  const onSubmit = (values: AdquisicionFormData) => {
    registrar.mutate(
      {
        tipo:                values.tipo,
        numero_documento:    values.numero_documento,
        proveedor_o_donante: values.proveedor_o_donante,
        fecha_adquisicion:   values.fecha_adquisicion,
        observaciones:       values.observaciones || null,
        items: values.items.map(item => ({
          inventario_medicina_id: item.inventario_medicina_id,
          cantidad:                item.cantidad,
          lote:                    item.lote || null,
          fecha_caducidad:         item.fecha_caducidad || null,
          precio_unitario:         item.precio_unitario || null,
        })),
      },
      { onSuccess: (adquisicion) => onCreada(adquisicion) }
    )
  }

  return (
    <>
      <Card withBorder radius="lg" p="xl">
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="md">
            <Group grow>
              <Controller
                name="tipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo de adquisición"
                    data={TIPO_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? 'compra')}
                  />
                )}
              />
              <Controller
                name="fecha_adquisicion"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de adquisición"
                    valueFormat="DD/MM/YYYY"
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(d) => field.onChange(fromDate(d))}
                    error={errors.fecha_adquisicion?.message}
                  />
                )}
              />
            </Group>

            <Group grow>
              <TextInput
                label="N° de documento"
                placeholder="Ej: CONT-2026-0045, FACT-001234"
                {...contained}
                {...register('numero_documento')}
                error={errors.numero_documento?.message}
              />
              <TextInput
                label="Proveedor / Donante"
                placeholder="Ej: Farmaenlace S.A."
                {...contained}
                {...register('proveedor_o_donante')}
                error={errors.proveedor_o_donante?.message}
              />
            </Group>

            <Textarea
              label="Observaciones (opcional)"
              autosize
              minRows={2}
              {...contained}
              {...register('observaciones')}
            />

            <Stack gap="xs">
              <Text size="sm" fw={500}>Medicamentos</Text>

              <BuscarMedicinaSelect
                onSeleccionar={(id, nombre) => {
                  append({
                    inventario_medicina_id: id,
                    nombre_medicina: nombre,
                    cantidad: 1,
                    lote: '',
                    fecha_caducidad: '',
                    precio_unitario: null,
                  })
                }}
                onCrearNueva={abrirMedicinaModal}
              />

              {fields.length === 0 ? (
                <Alert
                  icon={<IconInfoCircle size={14} />}
                  color="gray"
                  variant="light"
                >
                  <Text size="xs">
                    Busca y agrega al menos un medicamento
                    a esta adquisición.
                  </Text>
                </Alert>
              ) : (
                <Table withTableBorder withColumnBorders>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>Medicina</Table.Th>
                      <Table.Th w={90}>Cantidad</Table.Th>
                      <Table.Th w={120}>Lote</Table.Th>
                      <Table.Th w={140}>Caducidad</Table.Th>
                      <Table.Th w={110}>Precio unit.</Table.Th>
                      <Table.Th w={40}></Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {fields.map((field, i) => (
                      <ItemAdquisicionRow
                        key={field.id}
                        index={i}
                        control={control}
                        nombre={field.nombre_medicina}
                        onEliminar={() => remove(i)}
                      />
                    ))}
                  </Table.Tbody>
                </Table>
              )}

              {errors.items?.message && (
                <Text size="xs" c="red">
                  {errors.items.message}
                </Text>
              )}
            </Stack>

            <Group justify="flex-end" mt="sm">
              <Button
                type="submit"
                color="emerald"
                leftSection={<IconCheck size={14} />}
                loading={registrar.isPending}
              >
                Registrar adquisición
              </Button>
            </Group>
          </Stack>
        </form>
      </Card>

      <MedicinaModal
        opened={medicinaModalOpened}
        onClose={cerrarMedicinaModal}
        initialValues={null}
        onCreated={(medicina) => {
          append({
            inventario_medicina_id: medicina.id,
            nombre_medicina: medicina.nombre,
            cantidad: 1,
            lote: medicina.lote ?? '',
            fecha_caducidad: medicina.fecha_caducidad ?? '',
            precio_unitario: null,
          })
        }}
      />
    </>
  )
}
