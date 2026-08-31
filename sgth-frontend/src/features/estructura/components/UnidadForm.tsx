'use client'

import {
  TextInput, Select, Textarea,
  NumberInput, Grid,
} from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTiposUnidad } from '../hooks/useTiposUnidad'
import { useUnidades } from '../hooks/useUnidades'
import { unidadSchema, type UnidadFormData } from '../schemas/unidad.schema'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  initialValues?: Partial<UnidadFormData>
  onSubmit: (values: UnidadFormData) => void
  isPending: boolean
  submitLabel?: string
}

export function UnidadForm({ initialValues, onSubmit }: Props) {
  const contained = useContainedInput()
  const { data: tiposRaw }    = useTiposUnidad()
  const { data: unidadesRaw } = useUnidades()

  const tipos    = tiposRaw    ?? []
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<UnidadFormData>({
    resolver: zodResolver(unidadSchema) as Resolver<UnidadFormData>,
    defaultValues: {
      nombre:             initialValues?.nombre             ?? '',
      codigo:             initialValues?.codigo             ?? '',
      tipo_unidad_id:     initialValues?.tipo_unidad_id     ?? undefined,
      unidad_padre_id:    initialValues?.unidad_padre_id    ?? null,
      mision:             initialValues?.mision             ?? '',
      presupuesto_total:  initialValues?.presupuesto_total  ?? null,
    },
  })

  const tipoOptions = tipos.map(t => ({
    value: String(t.id),
    label: t.descripcion ?? t.acronimo ?? `Tipo ${t.id}`,
  }))

  const unidadOptions = [
    { value: '', label: 'Sin unidad padre (raíz)' },
    ...unidades.map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  return (
    <form noValidate id="unidad-form" onSubmit={handleSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput
            label="Nombre"
            placeholder="Nombre de la unidad"
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TextInput
            label="Código"
            placeholder="Ej: UATH-001"
            {...contained}
            {...register('codigo')}
            error={errors.codigo?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="tipo_unidad_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de unidad"
                placeholder="Seleccionar tipo"
                data={tipoOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : ''}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.tipo_unidad_id?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="unidad_padre_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad padre"
                placeholder="Sin unidad padre (raíz)"
                data={unidadOptions}
                searchable
                clearable
                {...contained}
                value={field.value ? String(field.value) : ''}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Misión"
            placeholder="Misión de la unidad administrativa"
            rows={3}
            {...contained}
            {...register('mision')}
            error={errors.mision?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="presupuesto_total"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Presupuesto total"
                placeholder="0.00"
                decimalScale={2}
                prefix="$"
                {...contained}
                value={field.value ?? ''}
                onChange={(v) =>
                  field.onChange(typeof v === 'number' ? v : null)
                }
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
