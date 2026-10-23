'use client'

import { Alert, Grid, Select, Stack, Switch, Text, TextInput } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogoExamenes } from '../../hooks/useExamenes'
import { useGuardarPerfilExamen } from '../../hooks/useExamenesAdmin'
import { perfilExamenSchema, type PerfilExamenFormData } from '../../schemas/examenAdmin.schema'
import { ETIQUETA_ESPECIALIDAD, type Especialidad } from '../../services/kpisService'
import { CatalogoExamenesCasillas } from './CatalogoExamenesCasillas'
import type { CatalogoExamen, PerfilExamen } from '../../services/examenService'

interface Props {
  opened:         boolean
  onClose:        () => void
  perfil:         PerfilExamen | null
  /** Las que puede armar quien abre: la administración, las dos. */
  especialidades: Especialidad[]
}

/** Un perfil: el grupo de exámenes que se marca de una vez al pedir. */
export function PerfilExamenModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, perfil, especialidades }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const catalogo = useCatalogoExamenes()
  const guardar = useGuardarPerfilExamen()

  // El servidor solo admite exámenes activos: los retirados del catálogo se
  // quitan del perfil al guardarlo, y se avisa.
  const retirados = perfil?.examenes.filter((e) => !e.activo) ?? []

  const { register, control, handleSubmit, setValue, setError, formState: { errors } } = useForm<PerfilExamenFormData>({
    resolver: zodResolver(perfilExamenSchema),
    defaultValues: {
      especialidad: perfil?.especialidad ?? especialidades[0] ?? 'medicina_general',
      nombre:       perfil?.nombre ?? '',
      descripcion:  perfil?.descripcion ?? '',
      ocupacional:  perfil?.ocupacional ?? false,
      activo:       perfil?.activo ?? true,
      examenes:     perfil?.examenes.filter((e) => e.activo).map((e) => e.id) ?? [],
    },
  })
  const examenes = useWatch({ control, name: 'examenes' })
  const marcados = new Set(examenes)

  const alternar = (examen: CatalogoExamen) => setValue(
    'examenes',
    marcados.has(examen.id) ? examenes.filter((id) => id !== examen.id) : [...examenes, examen.id],
    { shouldValidate: errors.examenes !== undefined },
  )

  const enviar = (v: PerfilExamenFormData) => {
    const sensibles = (catalogo.data?.examenes ?? []).filter((e) => e.sensible && v.examenes.includes(e.id))
    if (v.ocupacional && sensibles.length > 0) {
      setError('examenes', {
        message: `Un perfil ocupacional no puede llevar exámenes sensibles: quite ${sensibles.map((e) => e.nombre).join(', ')}.`,
      })
      return
    }
    guardar.mutateAsync({ id: perfil?.id, data: { ...v, descripcion: v.descripcion.trim() || null } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(perfilExamenSchema.shape), 'No se pudo guardar el perfil'))
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={perfil ? 'Editar perfil' : 'Nuevo perfil'}
      size="90%"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(enviar)}
      submitLabel={perfil ? 'Guardar cambios' : `Crear perfil (${examenes.length})`}
      submitting={guardar.isPending}
    >
      <Stack gap="md">
        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="Nombre" placeholder="Ej.: Control metabólico" withAsterisk maxLength={100}
              {...contained} {...register('nombre')} error={errors.nombre?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 3 }}>
            <Controller name="especialidad" control={control} render={({ field }) => (
              <Select label="Especialidad" withAsterisk allowDeselect={false} {...contained}
                data={especialidades.map((e) => ({ value: e, label: ETIQUETA_ESPECIALIDAD[e] }))}
                value={field.value} onChange={(v) => v && field.onChange(v)} error={errors.especialidad?.message} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 5 }}>
            <TextInput label="Descripción" placeholder="Opcional. Cuándo usarlo" maxLength={300}
              {...contained} {...register('descripcion')} error={errors.descripcion?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="ocupacional" control={control} render={({ field }) => (
              <Switch label="Ocupacional" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
                description="Para evaluaciones de salud ocupacional: no admite exámenes sensibles (VIH, embarazo, drogas)." />
            )} />
          </Grid.Col>
          {perfil && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="activo" control={control} render={({ field }) => (
                <Switch label="Activo" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
                  description="Inactivo, deja de ofrecerse al pedir exámenes." />
              )} />
            </Grid.Col>
          )}
        </Grid>

        {retirados.length > 0 && (
          <Alert color="amber" variant="light">
            Al guardar se quitarán {retirados.length === 1 ? 'un examen retirado' : `${retirados.length} exámenes retirados`} del
            catálogo: {retirados.map((e) => e.nombre).join(', ')}.
          </Alert>
        )}

        <SectionHeading title={`Exámenes del perfil (${examenes.length})`} />
        {errors.examenes?.message && <Text size="xs" c="red">{errors.examenes.message}</Text>}
        <DataState loading={catalogo.isLoading} error={catalogo.error} onRetry={() => catalogo.refetch()}
          errorTitle="No se pudo cargar el catálogo de exámenes" skeletonRows={4}>
          <CatalogoExamenesCasillas examenes={catalogo.data?.examenes ?? []} areas={catalogo.data?.areas ?? []}
            marcados={marcados} onAlternar={alternar} />
        </DataState>
      </Stack>
    </FormModal>
  )
}
