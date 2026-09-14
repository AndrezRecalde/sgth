'use client'

import { confirmar, SgthModal, StatusBadge } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Group, TextInput, Select, Button,
  ActionIcon, } from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SgthTable } from '@/components/ui/SgthTable'
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
  const { data: factores = [], isLoading } = useFactoresRiesgo()
  const { crear, eliminar } = useFactorRiesgoMutations()

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<FactorRiesgoFormData>({
    resolver: zodResolver(factorRiesgoSchema) as Resolver<FactorRiesgoFormData>,
    defaultValues: { nombre: '', categoria: 'fisico' },
  })

  const [confirmarId, setConfirmarId] = useState<number | null>(null)

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
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (f) => (
        <ActionIcon
          color="red"
          variant="subtle"
          loading={eliminar.isPending && confirmarId === f.id}
          onClick={() => confirmar({
            title:   'Eliminar factor de riesgo',
            message: <>Se eliminará el factor <b>{f.nombre}</b>. No se puede deshacer.</>,
            destructiva: true,
            onConfirm: () => {
              setConfirmarId(f.id)
              eliminar.mutate(f.id)
            },
          })}
        >
          <IconTrash size={16} />
        </ActionIcon>
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

        <SgthTable
          records={factores}
          columns={columns}
          fetching={isLoading}
          noRecordsText="Sin factores registrados todavía."
          minHeight={120}
        />
      </Stack>
    </SgthModal>
  )
}
