'use client'

import { TextInput, Select, Switch, Grid } from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '../hooks/useUnidades'
import { extensionSchema, type ExtensionFormData } from '../schemas/extension.schema'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  initialValues?: Partial<ExtensionFormData>
  onSubmit: (values: ExtensionFormData) => void
}

export function ExtensionForm({ initialValues, onSubmit }: Props) {
  const contained = useContainedInput()
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ExtensionFormData>({
    resolver: zodResolver(extensionSchema) as Resolver<ExtensionFormData>,
    defaultValues: {
      unidad_administrativa_id: initialValues?.unidad_administrativa_id ?? undefined,
      numero_extension:         initialValues?.numero_extension ?? '',
      responsable:              initialValues?.responsable ?? '',
      estado:                   initialValues?.estado ?? true,
    },
  })

  const unidadOptions = unidades.map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  return (
    <form noValidate id="extension-form" onSubmit={handleSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={12}>
          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                placeholder="Seleccionar unidad"
                data={unidadOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : ''}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.unidad_administrativa_id?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Número de extensión"
            placeholder="Ej: 1234"
            maxLength={10}
            {...contained}
            {...register('numero_extension')}
            error={errors.numero_extension?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Responsable"
            placeholder="Nombre del responsable"
            {...contained}
            {...register('responsable')}
            error={errors.responsable?.message}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Switch
                label="Extensión activa"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                color="emerald"
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
