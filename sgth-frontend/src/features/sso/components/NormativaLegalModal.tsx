'use client'

import { confirmar } from '@/components/ui'
import {
  Modal, Stack, Group, TextInput, Select, Textarea, Button,
  ActionIcon, Badge,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
import { useNormativas, useNormativaMutations } from '../hooks/useNormativaLegal'
import {
  normativaLegalSchema, type NormativaLegalFormData, TIPO_NORMATIVA_OPTIONS,
} from '../schemas/normativaLegal.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { NormativaLegalSso } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function NormativaLegalModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { data: normativas = [], isLoading } = useNormativas()
  const { crear, eliminar } = useNormativaMutations()

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
      render: (n) => <Badge variant="light" size="sm">{getTipoLabel(n.tipo)}</Badge>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (n) => (
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => confirmar({
            title:   'Eliminar normativa',
            message: <>Se eliminará la normativa <b>{n.nombre}</b>. No se puede deshacer.</>,
            destructiva: true,
            onConfirm: () => eliminar.mutate(n.id),
          })}
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
      title="Catálogo de normativa legal SSO"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Stack gap="sm">
            <Group align="flex-end" wrap="nowrap">
              <TextInput
                label="Nombre de la normativa"
                placeholder="Ej: Reglamento de Seguridad y Salud (Decreto 2393)"
                style={{ flex: 1 }}
                {...contained}
                {...register('nombre')}
                error={errors.nombre?.message}
              />
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
                    style={{ minWidth: 160 }}
                  />
                )}
              />
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
                    style={{ width: 170 }}
                  />
                )}
              />
            </Group>
            <Group align="flex-end" wrap="nowrap">
              <Textarea
                label="Descripción (opcional)"
                rows={2}
                style={{ flex: 1 }}
                {...contained}
                {...register('descripcion')}
                error={errors.descripcion?.message}
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
          </Stack>
        </form>

        <SgthTable
          records={normativas}
          columns={columns}
          fetching={isLoading}
          noRecordsText="Sin normativas registradas todavía."
          minHeight={120}
        />
      </Stack>
    </Modal>
  )
}
