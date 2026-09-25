'use client'

import { confirmar, DataState, SgthModal, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Grid, Group, TextInput, Select, Textarea, Button, Switch,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus, IconGavel, IconEyeOff, IconEye } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useNormativas, useNormativaMutations } from '../hooks/useNormativaLegal'
import {
  normativaLegalSchema, type NormativaLegalFormData, TIPO_NORMATIVA_OPTIONS,
} from '../schemas/normativaLegal.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { NormativaLegalSso } from '../services/tipos'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function NormativaLegalModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  // El catálogo muestra las activas; las inactivas se piden a propósito, que es
  // la única forma de volver a activar una.
  const [verInactivas, setVerInactivas] = useState(false)
  const { data: normativas = [], isLoading, error, refetch } = useNormativas({ solo_activas: !verInactivas })
  const { crear, cambiarActivo, eliminar } = useNormativaMutations()

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<NormativaLegalFormData>({
    resolver: zodResolver(normativaLegalSchema) as Resolver<NormativaLegalFormData>,
    defaultValues: { nombre: '', tipo: 'reglamento', fecha_vigencia: '', descripcion: '' },
  })

  const getTipoLabel = (valor: string) =>
    TIPO_NORMATIVA_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const onSubmit = (values: NormativaLegalFormData) => {
    crear.mutateAsync(values).then(() => reset({ nombre: '', tipo: values.tipo, fecha_vigencia: '', descripcion: '' })).catch(() => {})
  }

  const columns: DataTableColumn<NormativaLegalSso>[] = [
    { accessor: 'nombre', title: 'Normativa' },
    {
      accessor: 'tipo',
      title: 'Tipo',
      width: 160,
      render: (n) => <StatusBadge>{getTipoLabel(n.tipo)}</StatusBadge>,
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 100,
      render: (n) => (
        <StatusBadge tone={n.activo ? 'success' : 'neutral'}>
          {n.activo ? 'Activa' : 'Inactiva'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (n) => (
        <TableActions
          actions={[
            {
              label: n.activo ? 'Desactivar' : 'Reactivar',
              icon: n.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
              onClick: () => cambiarActivo.mutate({ id: n.id, activo: !n.activo }),
            },
            {
              label: 'Eliminar normativa',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar normativa',
                message: (
                  <>
                    Se eliminará la normativa <b>{n.nombre}</b>. No se puede deshacer.
                    Si ya tiene cumplimiento registrado, desactívela en vez de borrarla:
                    eliminarla se llevaría ese historial.
                  </>
                ),
                destructiva: true,
                onConfirm: () => eliminar.mutate(n.id),
              }),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Catálogo de normativa legal SSO"
      // `xl` y no `lg`: el título oficial de un reglamento no cabía ni
      // ocupando la fila entera, y la tabla de abajo lista esos mismos
      // títulos.
      size="xl"
    >
      <Stack gap="md">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          {/* El nombre de la normativa se lleva la fila entera: los
              títulos oficiales son largos —«Reglamento de Seguridad y Salud de
              los Trabajadores y Mejoramiento del Medio Ambiente de Trabajo»
              necesita 675 px— y compartiendo fila con el tipo, la vigencia y
              el botón tenía 90. */}
          <Grid gap="sm">
            <Grid.Col span={12}>
              <TextInput
                label="Nombre de la normativa"
                placeholder="Ej: Reglamento de Seguridad y Salud (Decreto 2393)"
                {...contained}
                {...register('nombre')}
                error={errors.nombre?.message}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="tipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo"
                    data={TIPO_NORMATIVA_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v as NormativaLegalFormData['tipo'])}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="fecha_vigencia"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Vigencia"
                    placeholder="Seleccionar"
                    valueFormat="DD/MM/YYYY"
                    {...contained}
                    value={toDateValue(field.value)}
                    onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={12}>
              <Textarea
                label="Descripción (opcional)"
                rows={2}
                {...contained}
                {...register('descripcion')}
                error={errors.descripcion?.message}
              />
            </Grid.Col>
            <Grid.Col span={12}>
              <Group justify="flex-end">
                <Button
                  type="submit"
                  h={48}
                  leftSection={<IconPlus size={16} />}
                  loading={crear.isPending}
                >
                  Agregar
                </Button>
              </Group>
            </Grid.Col>
          </Grid>
        </form>

        <Group justify="flex-end">
          <Switch
            label="Ver inactivas"
            checked={verInactivas}
            onChange={(e) => setVerInactivas(e.currentTarget.checked)}
          />
        </Group>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar el catálogo de normativa"
          errorHint="No quiere decir que el catálogo esté vacío: no se pudo consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!normativas.length}
          emptyProps={{
            icon: IconGavel,
            title: 'Sin normativa registrada todavía',
            description: 'Agregue la primera con el formulario de arriba; la lista de verificación se arma con lo que haya aquí.',
          }}
        >
          <SgthTable
            records={normativas}
            columns={columns}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
