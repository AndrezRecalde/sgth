'use client'

import { confirmar, DataState, SgthModal, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Group, TextInput, Select, Button, Switch,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus, IconShieldCheck, IconEyeOff, IconEye } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useFactoresRiesgo, useFactorRiesgoMutations } from '../hooks/useFactoresRiesgo'
import {
  factorRiesgoSchema, type FactorRiesgoFormData, CATEGORIA_FACTOR_OPTIONS,
} from '../schemas/factorRiesgo.schema'
import type { FactorRiesgoCatalogo } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function FactoresRiesgoModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  // El catálogo muestra los activos; los inactivos se piden a propósito, que es
  // la única forma de volver a activar uno.
  const [verInactivos, setVerInactivos] = useState(false)
  const { data: factores = [], isLoading, error, refetch } =
    useFactoresRiesgo({ solo_activos: !verInactivos })
  const { crear, cambiarActivo, eliminar } = useFactorRiesgoMutations()

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<FactorRiesgoFormData>({
    resolver: zodResolver(factorRiesgoSchema) as Resolver<FactorRiesgoFormData>,
    defaultValues: { nombre: '', categoria: 'fisico' },
  })

  const getCategoriaLabel = (valor: string) =>
    CATEGORIA_FACTOR_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const onSubmit = (values: FactorRiesgoFormData) => {
    crear.mutateAsync(values).then(() => reset({ nombre: '', categoria: values.categoria })).catch(() => {})
  }

  const columns: DataTableColumn<FactorRiesgoCatalogo>[] = [
    { accessor: 'nombre', title: 'Factor' },
    {
      accessor: 'categoria',
      title: 'Categoría',
      width: 160,
      render: (f) => <StatusBadge>{getCategoriaLabel(f.categoria)}</StatusBadge>,
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 100,
      render: (f) => (
        <StatusBadge tone={f.activo ? 'success' : 'neutral'}>
          {f.activo ? 'Activo' : 'Inactivo'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (f) => (
        <TableActions
          actions={[
            {
              label: f.activo ? 'Desactivar' : 'Reactivar',
              icon: f.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
              onClick: () => cambiarActivo.mutate({ id: f.id, activo: !f.activo }),
            },
            {
              label: 'Eliminar factor',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar factor de riesgo',
                message: (
                  <>
                    Se eliminará el factor <b>{f.nombre}</b>. No se puede deshacer.
                    Si algún riesgo lo usa, desactívelo en vez de borrarlo.
                  </>
                ),
                destructiva: true,
                onConfirm: () => eliminar.mutate(f.id),
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
      title="Catálogo de factores de riesgo"
      size="lg"
    >
      <Stack gap="md">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Group align="flex-end" wrap="nowrap">
            <TextInput
              label="Nombre del factor"
              placeholder="Ej: Manejo manual de cargas"
              style={{ flex: 1 }}
              {...contained}
              {...register('nombre')}
              error={errors.nombre?.message}
            />
            <Controller
              name="categoria"
              control={control}
              render={({ field }) => (
                <Select
                  label="Categoría"
                  data={CATEGORIA_FACTOR_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v as FactorRiesgoFormData['categoria'])}
                  style={{ minWidth: 160 }}
                />
              )}
            />
            <Button
              type="submit"
              leftSection={<IconPlus size={16} />}
              loading={crear.isPending}
            >
              Agregar
            </Button>
          </Group>
        </form>

        <Group justify="flex-end">
          <Switch
            label="Ver inactivos"
            checked={verInactivos}
            onChange={(e) => setVerInactivos(e.currentTarget.checked)}
          />
        </Group>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar el catálogo de factores"
          errorHint="No quiere decir que el catálogo esté vacío: no se pudo consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!factores.length}
          emptyProps={{
            icon: IconShieldCheck,
            title: 'Sin factores registrados todavía',
            description: 'Agregue el primer factor con el formulario de arriba.',
          }}
        >
          <SgthTable
            records={factores}
            columns={columns}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
