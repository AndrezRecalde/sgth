'use client'

import { confirmar, DataState, SgthModal, SgthTable, StatusBadge } from '@/components/ui'
import {
  Stack, Group, TextInput, Select, Textarea, Button,
  ActionIcon, } from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus, IconChecklist } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useActividadesPrograma, useProgramaDrogasMutations } from '../hooks/useProgramaDrogas'
import {
  actividadProgramaSchema, type ActividadProgramaFormData, FASE_PROGRAMA_DROGAS_OPTIONS,
} from '../schemas/programaDrogas.schema'
import type { ProgramaDrogaActividad } from '../services/programaDrogasService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function CatalogoActividadesProgramaModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const { data: actividades = [], isLoading, error, refetch } = useActividadesPrograma()
  const { crearActividad, eliminarActividad } = useProgramaDrogasMutations()

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<ActividadProgramaFormData>({
    resolver: zodResolver(actividadProgramaSchema) as Resolver<ActividadProgramaFormData>,
    defaultValues: { nombre: '', fase: 'fase_1_preparacion', descripcion: '' },
  })

  const getFaseLabel = (valor: string) =>
    FASE_PROGRAMA_DROGAS_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const onSubmit = (values: ActividadProgramaFormData) => {
    crearActividad.mutateAsync(values).then(() => reset({ nombre: '', fase: values.fase, descripcion: '' })).catch(() => {})
  }

  const columns: DataTableColumn<ProgramaDrogaActividad>[] = [
    { accessor: 'nombre', title: 'Actividad' },
    {
      accessor: 'fase',
      title: 'Fase',
      width: 200,
      render: (a) => <StatusBadge>{getFaseLabel(a.fase)}</StatusBadge>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => (
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => confirmar({
            title:   'Eliminar actividad',
            message: <>Se eliminará la actividad <b>{a.nombre}</b>. No se puede deshacer.</>,
            destructiva: true,
            onConfirm: () => eliminarActividad.mutate(a.id),
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
      title="Catálogo de actividades del programa de drogas"
      size="lg"
    >
      <Stack gap="md">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Stack gap="sm">
            <Group align="flex-end" wrap="nowrap">
              <TextInput
                label="Nombre de la actividad"
                placeholder="Ej: Realizar campañas informativas sobre prácticas de vida saludable"
                style={{ flex: 1 }}
                {...contained}
                {...register('nombre')}
                error={errors.nombre?.message}
              />
              <Controller
                name="fase"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Fase"
                    data={FASE_PROGRAMA_DROGAS_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v as ActividadProgramaFormData['fase'])}
                    style={{ minWidth: 220 }}
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
                leftSection={<IconPlus size={16} />}
                loading={crearActividad.isPending}
              >
                Agregar
              </Button>
            </Group>
          </Stack>
        </form>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar el catálogo de actividades"
          errorHint="No quiere decir que el catálogo esté vacío: no se pudo consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!actividades.length}
          emptyProps={{
            icon: IconChecklist,
            title: 'Sin actividades registradas todavía',
            description: 'Agregue la primera con el formulario de arriba; la matriz de seguimiento se arma con lo que haya aquí.',
          }}
        >
          <SgthTable
            records={actividades}
            columns={columns}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
