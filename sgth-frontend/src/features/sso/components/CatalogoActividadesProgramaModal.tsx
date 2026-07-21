'use client'

import {
  Modal, Stack, Group, TextInput, Select, Textarea, Button,
  ActionIcon, Badge,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
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
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { data: actividades = [], isLoading } = useActividadesPrograma()
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
      render: (a) => <Badge variant="light" size="sm">{getFaseLabel(a.fase)}</Badge>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => (
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => {
            if (confirm(`¿Eliminar la actividad "${a.nombre}"?`)) {
              eliminarActividad.mutate(a.id)
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
      title="Catálogo de actividades del programa de drogas"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <form onSubmit={handleSubmit(onSubmit)}>
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
                color="emerald"
                leftSection={<IconPlus size={16} />}
                loading={crearActividad.isPending}
              >
                Agregar
              </Button>
            </Group>
          </Stack>
        </form>

        <SgthTable
          records={actividades}
          columns={columns}
          fetching={isLoading}
          noRecordsText="Sin actividades registradas todavía."
          minHeight={120}
        />
      </Stack>
    </Modal>
  )
}
