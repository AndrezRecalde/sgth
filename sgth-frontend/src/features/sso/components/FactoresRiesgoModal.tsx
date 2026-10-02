'use client'

import { confirmar, DataState, SgthModal, SgthTable, type TableAction } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Grid, Group, TextInput, Select, Button, Switch,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconTrash, IconPlus, IconShieldCheck, IconEyeOff, IconEye, IconEdit, IconDeviceFloppy,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useFactoresRiesgo, useFactorRiesgoMutations } from '../hooks/useFactoresRiesgo'
import {
  factorRiesgoSchema, type FactorRiesgoFormData, CATEGORIA_FACTOR_OPTIONS,
} from '../schemas/factorRiesgo.schema'
import { columnasFactorRiesgo } from './factorRiesgo.columns'
import type { FactorRiesgoCatalogo } from '../services/tipos'
import { erroresDeCampo } from '@/lib/erroresDeCampo'

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
  const { crear, editar, cambiarActivo, eliminar } = useFactorRiesgoMutations()
  // Null es «alta»; con un factor dentro, el mismo formulario edita ese.
  const [editando, setEditando] = useState<FactorRiesgoCatalogo | null>(null)

  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<FactorRiesgoFormData>({
    resolver: zodResolver(factorRiesgoSchema) as Resolver<FactorRiesgoFormData>,
    defaultValues: { nombre: '', categoria: 'fisico' },
  })

  const empezarEdicion = (factor: FactorRiesgoCatalogo) => {
    setEditando(factor)
    reset({ nombre: factor.nombre, categoria: factor.categoria as FactorRiesgoFormData['categoria'] })
  }

  const cancelarEdicion = () => {
    setEditando(null)
    reset({ nombre: '', categoria: 'fisico' })
  }

  // El 422 del backend, en su campo. En un solo sitio porque lo usan las dos
  // ramas del envío: dejarlo solo en el alta —que es donde estaba cuando se
  // escribió este cambio— dejaría la edición notificando por encima.
  const marcarErrores = (error: unknown) => {
    const campos = erroresDeCampo(error)
    if (!campos) return // el hook ya lo notificó
    for (const [campo, mensaje] of Object.entries(campos)) {
      setError(campo as keyof FactorRiesgoFormData, { message: mensaje })
    }
  }

  const onSubmit = (values: FactorRiesgoFormData) => {
    if (editando) {
      editar.mutateAsync({ id: editando.id, ...values }).then(cancelarEdicion).catch(marcarErrores)
      return
    }
    crear.mutateAsync(values).then(() => reset({ nombre: '', categoria: values.categoria })).catch(marcarErrores)
  }

  const accionesDe = (f: FactorRiesgoCatalogo): TableAction[] => [
    {
      label: 'Editar factor',
      icon: <IconEdit size={14} />,
      onClick: () => empezarEdicion(f),
    },
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
          {/* El nombre del factor se lleva la fila entera: «Exposición a
              polvo de sílice en el corte de adoquín» necesita 335 px y
              compartiendo fila con la categoría y el botón tenía 157. */}
          <Grid gap="sm">
            <Grid.Col span={12}>
              <TextInput
                label="Nombre del factor"
                placeholder="Ej: Manejo manual de cargas"
                {...contained}
                {...register('nombre')}
                error={errors.nombre?.message}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 8 }}>
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
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Button
                type="submit"
                h={48}
                fullWidth
                leftSection={editando ? <IconDeviceFloppy size={16} /> : <IconPlus size={16} />}
                loading={crear.isPending || editar.isPending}
              >
                {editando ? 'Guardar cambios' : 'Agregar'}
              </Button>
            </Grid.Col>
          </Grid>
        </form>

        <Group justify="space-between">
          {/* La salida de la edición: sin ella, el formulario se queda
              apuntando a un factor y el siguiente «Guardar cambios» lo
              pisa en vez de dar de alta uno nuevo. */}
          {editando ? (
            <Button variant="subtle" size="xs" onClick={cancelarEdicion}>
              Cancelar la edición de «{editando.nombre}»
            </Button>
          ) : <span />}
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
            columns={columnasFactorRiesgo(accionesDe)}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
