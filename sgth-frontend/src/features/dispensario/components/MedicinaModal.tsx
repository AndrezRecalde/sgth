'use client'

import { useEffect } from 'react'
import {
  Modal, Stack, Group, TextInput,
  NumberInput, Button, Select,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useInventarioMutations } from '../hooks/useInventarioMedicina'
import {
  medicinaSchema, type MedicinaFormData,
  PRESENTACION_OPTIONS,
} from '../schemas/inventarioMedicina.schema'
import type { InventarioMedicina } from '../services/inventarioMedicinaService'

interface Props {
  opened:         boolean
  onClose:        () => void
  initialValues?: InventarioMedicina | null
  onCreated?:     (medicina: InventarioMedicina) => void
}

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

export function MedicinaModal({
  opened, onClose, initialValues, onCreated,
}: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()
  const { crear, actualizar } = useInventarioMutations()
  const isEditing = !!initialValues

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<MedicinaFormData>({
    resolver: zodResolver(medicinaSchema),
    defaultValues: {
      nombre: '', principio_activo: '',
      presentacion: '', concentracion: '',
      stock_actual: 0, stock_minimo: 0,
      fecha_caducidad: '', lote: '',
    },
  })

  useEffect(() => {
    if (opened) {
      reset(initialValues ? {
        nombre:           initialValues.nombre,
        principio_activo: initialValues.principio_activo,
        presentacion:     initialValues.presentacion,
        concentracion:    initialValues.concentracion ?? '',
        stock_actual:     initialValues.stock_actual,
        stock_minimo:     initialValues.stock_minimo,
        fecha_caducidad:  initialValues.fecha_caducidad ?? '',
        lote:             initialValues.lote ?? '',
      } : {
        nombre: '', principio_activo: '',
        presentacion: '', concentracion: '',
        stock_actual: 0, stock_minimo: 0,
        fecha_caducidad: '', lote: '',
      })
    }
  }, [opened, initialValues, reset])

  const onSubmit = (values: MedicinaFormData) => {
    const promise = isEditing && initialValues
      ? actualizar.mutateAsync({
          id: initialValues.id,
          data: {
            nombre:           values.nombre,
            principio_activo: values.principio_activo,
            presentacion:     values.presentacion,
            concentracion:    values.concentracion || null,
            stock_minimo:     values.stock_minimo,
            fecha_caducidad:  values.fecha_caducidad || null,
            lote:             values.lote || null,
          },
        })
      : crear.mutateAsync({
          ...values,
          concentracion:   values.concentracion || null,
          fecha_caducidad: values.fecha_caducidad || null,
          lote:            values.lote || null,
        })

    promise.then((medicina) => {
      reset()
      onClose()
      if (!isEditing && onCreated) {
        onCreated(medicina as InventarioMedicina)
      }
    }).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar medicina' : 'Registrar medicina'}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          {isEditing && initialValues && (
            <TextInput
              label="Código"
              value={initialValues.codigo}
              disabled
              description="Generado automáticamente"
              {...contained}
            />
          )}

          <Group grow align="flex-start">
            <TextInput
              label="Nombre comercial"
              {...contained}
              {...register('nombre')}
              error={errors.nombre?.message}
            />
            <TextInput
              label="Principio activo"
              {...contained}
              {...register('principio_activo')}
              error={errors.principio_activo?.message}
            />
          </Group>

          <Group grow align="flex-start">
            <Controller
              name="presentacion"
              control={control}
              render={({ field }) => (
                <Select
                  label="Presentación"
                  placeholder="Seleccione"
                  data={PRESENTACION_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v ?? '')}
                  error={errors.presentacion?.message}
                />
              )}
            />
            <TextInput
              label="Concentración (opcional)"
              placeholder="Ej: 500mg"
              {...contained}
              {...register('concentracion')}
            />
          </Group>

          <Group grow align="flex-start">
            <TextInput
              label="Lote (opcional)"
              {...contained}
              {...register('lote')}
            />
            <Controller
              name="fecha_caducidad"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha de caducidad (opcional)"
                  valueFormat="DD/MM/YYYY"
                  clearable
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d))}
                />
              )}
            />
          </Group>

          <Group grow align="flex-start">
            <Controller
              name="stock_actual"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Stock inicial"
                  disabled={isEditing}
                  description={isEditing
                    ? 'Use "Ingresar stock" para modificar'
                    : undefined}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || 0)}
                  error={errors.stock_actual?.message}
                />
              )}
            />
            <Controller
              name="stock_minimo"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Stock mínimo"
                  description="Alerta cuando baje de este nivel"
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || 0)}
                  error={errors.stock_minimo?.message}
                />
              )}
            />
          </Group>

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={crear.isPending || actualizar.isPending}
            >
              {isEditing ? 'Guardar cambios' : 'Registrar medicina'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
