'use client'

import { useState } from 'react'
import {
  Modal, Stack, Group, TextInput, Select, Button,
  ActionIcon, Badge,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
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
  const { isMobile } = useMobileBreakpoint()
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
      render: (f) => <Badge variant="light" size="sm">{getCategoriaLabel(f.categoria)}</Badge>,
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
          onClick={() => {
            if (confirm(`¿Eliminar el factor "${f.nombre}"?`)) {
              setConfirmarId(f.id)
              eliminar.mutate(f.id)
            }
          }}
        >
          <IconTrash size={16} />
        </ActionIcon>
      ),
    },
  ]

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Catálogo de factores de riesgo"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <form noValidate onSubmit={handleSubmit(onSubmit)}>
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
              color="emerald"
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
    </Modal>
  )
}
