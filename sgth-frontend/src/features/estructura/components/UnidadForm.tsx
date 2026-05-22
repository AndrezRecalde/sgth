'use client'

import { TextInput, Select, Textarea, NumberInput, Grid } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
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

export function UnidadForm({
  initialValues,
  onSubmit,
  isPending,
  submitLabel = 'Guardar',
}: Props) {
  const contained = useContainedInput()
  const { data: tipos = [] } = useTiposUnidad()
  const { data: unidades = [] } = useUnidades()

  const form = useForm<UnidadFormData>({
    initialValues: {
      nombre:            initialValues?.nombre ?? '',
      codigo:            initialValues?.codigo ?? '',
      tipo_unidad_id:    initialValues?.tipo_unidad_id ?? ('' as unknown as number),
      unidad_padre_id:   initialValues?.unidad_padre_id ?? null,
      mision:            initialValues?.mision ?? '',
      presupuesto_total: initialValues?.presupuesto_total ?? null,
    },
    validate: zodResolver(unidadSchema),
  })

  const tipoOptions = (tipos as unknown as { id: number; nombre: string }[]).map(t => ({
    value: String(t.id),
    label: t.nombre ?? `Tipo ${t.id}`,
  }))

  const unidadOptions = [
    { value: '', label: 'Sin unidad padre (raíz)' },
    ...(unidades as unknown as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  return (
    <form id="unidad-form" onSubmit={form.onSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput
            label="Nombre"
            placeholder="Nombre de la unidad"
            {...contained}
            {...form.getInputProps('nombre')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TextInput
            label="Código"
            placeholder="Ej: UATH-001"
            {...contained}
            {...form.getInputProps('codigo')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Select
            label="Tipo de unidad"
            placeholder="Seleccionar tipo"
            data={tipoOptions}
            searchable
            {...contained}
            value={form.values.tipo_unidad_id
              ? String(form.values.tipo_unidad_id) : ''}
            onChange={(v) =>
              form.setFieldValue('tipo_unidad_id', v ? Number(v) : ('' as unknown as number))
            }
            error={form.errors.tipo_unidad_id}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Select
            label="Unidad padre"
            placeholder="Sin unidad padre (raíz)"
            data={unidadOptions}
            searchable
            clearable
            {...contained}
            value={form.values.unidad_padre_id
              ? String(form.values.unidad_padre_id) : ''}
            onChange={(v) =>
              form.setFieldValue('unidad_padre_id', v ? Number(v) : null)
            }
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Misión"
            placeholder="Misión de la unidad administrativa"
            rows={3}
            {...contained}
            {...form.getInputProps('mision')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <NumberInput
            label="Presupuesto total"
            placeholder="0.00"
            decimalScale={2}
            prefix="$"
            {...contained}
            value={form.values.presupuesto_total ?? ''}
            onChange={(v) =>
              form.setFieldValue('presupuesto_total',
                typeof v === 'number' ? v : null)
            }
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
