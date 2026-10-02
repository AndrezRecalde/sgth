'use client'

import { confirmar, DataState, SgthModal, SgthTable, type TableAction } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Grid, Group, TextInput, Select, Textarea, Button, Switch,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconTrash, IconPlus, IconChecklist, IconEyeOff, IconEye, IconEdit, IconDeviceFloppy,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useActividadesPrograma, useProgramaDrogasMutations } from '../hooks/useProgramaDrogas'
import {
  actividadProgramaSchema, type ActividadProgramaFormData, FASE_PROGRAMA_DROGAS_OPTIONS,
} from '../schemas/programaDrogas.schema'
import { columnasActividadPrograma } from './actividadPrograma.columns'
import type { ProgramaDrogaActividad } from '../services/programaDrogasService'
import { erroresDeCampo } from '@/lib/erroresDeCampo'

interface Props {
  opened: boolean
  onClose: () => void
}

export function CatalogoActividadesProgramaModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  // La matriz se arma con las activas; las inactivas se piden a propósito, que
  // es la única forma de volver a activar una.
  const [verInactivas, setVerInactivas] = useState(false)
  const { data: actividades = [], isLoading, error, refetch } =
    useActividadesPrograma({ solo_activas: !verInactivas })
  const {
    crearActividad, editarActividad, cambiarActivoActividad, eliminarActividad,
  } = useProgramaDrogasMutations()
  // Null es «alta»; con una actividad dentro, el mismo formulario edita esa.
  const [editando, setEditando] = useState<ProgramaDrogaActividad | null>(null)

  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<ActividadProgramaFormData>({
    resolver: zodResolver(actividadProgramaSchema) as Resolver<ActividadProgramaFormData>,
    defaultValues: { nombre: '', fase: 'fase_1_preparacion', descripcion: '' },
  })

  const empezarEdicion = (actividad: ProgramaDrogaActividad) => {
    setEditando(actividad)
    reset({
      nombre: actividad.nombre,
      fase: actividad.fase as ActividadProgramaFormData['fase'],
      descripcion: actividad.descripcion ?? '',
    })
  }

  const cancelarEdicion = () => {
    setEditando(null)
    reset({ nombre: '', fase: 'fase_1_preparacion', descripcion: '' })
  }

  // El 422 del backend, en su campo. En un solo sitio porque lo usan las dos
  // ramas del envío: dejarlo solo en el alta —que es donde estaba cuando se
  // escribió este cambio— dejaría la edición notificando por encima.
  const marcarErrores = (error: unknown) => {
    const campos = erroresDeCampo(error)
    if (!campos) return // el hook ya lo notificó
    for (const [campo, mensaje] of Object.entries(campos)) {
      setError(campo as keyof ActividadProgramaFormData, { message: mensaje })
    }
  }

  const onSubmit = (values: ActividadProgramaFormData) => {
    if (editando) {
      editarActividad.mutateAsync({ id: editando.id, ...values }).then(cancelarEdicion).catch(marcarErrores)
      return
    }
    crearActividad.mutateAsync(values).then(() => reset({ nombre: '', fase: values.fase, descripcion: '' })).catch(marcarErrores)
  }

  const accionesDe = (a: ProgramaDrogaActividad): TableAction[] => [
    {
      label: 'Editar actividad',
      icon: <IconEdit size={14} />,
      onClick: () => empezarEdicion(a),
    },
    {
      label: a.activo ? 'Desactivar' : 'Reactivar',
      icon: a.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
      onClick: () => cambiarActivoActividad.mutate({ id: a.id, activo: !a.activo }),
    },
    {
      label: 'Eliminar actividad',
      icon: <IconTrash size={14} />,
      color: 'red',
      onClick: () => confirmar({
        title:   'Eliminar actividad',
        message: (
          <>
            Se eliminará la actividad <b>{a.nombre}</b>. No se puede deshacer.
            Si ya tiene seguimiento registrado, desactívela en vez de borrarla:
            eliminarla se llevaría ese historial.
          </>
        ),
        destructiva: true,
        onConfirm: () => eliminarActividad.mutate(a.id),
      }),
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
          {/* El nombre de la actividad se lleva la fila entera: son frases
              —«Capacitación sobre prevención del consumo de alcohol y
              tabaco»— y compartiendo fila con la fase tenía 276 px de 428. */}
          <Grid gap="sm">
            <Grid.Col span={12}>
              <TextInput
                label="Nombre de la actividad"
                placeholder="Ej: Realizar campañas informativas sobre prácticas de vida saludable"
                {...contained}
                {...register('nombre')}
                error={errors.nombre?.message}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
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
                {editando && (
                  <Button variant="subtle" h={48} onClick={cancelarEdicion}>
                    Cancelar
                  </Button>
                )}
                <Button
                  type="submit"
                  h={48}
                  leftSection={editando ? <IconDeviceFloppy size={16} /> : <IconPlus size={16} />}
                  loading={crearActividad.isPending || editarActividad.isPending}
                >
                  {editando ? 'Guardar cambios' : 'Agregar'}
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
            columns={columnasActividadPrograma(accionesDe)}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
