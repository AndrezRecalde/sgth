'use client'

import { TextInput, Select, NumberInput, Grid } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '../hooks/useUnidades'
import { puestoSchema, type PuestoFormData } from '../schemas/puesto.schema'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  initialValues?: Partial<PuestoFormData>
  onSubmit: (values: PuestoFormData) => void
}

export function PuestoForm({ initialValues, onSubmit }: Props) {
  const contained = useContainedInput()
  const { data: unidades = [] } = useUnidades()

  const form = useForm<PuestoFormData>({
    initialValues: {
      nombre:                   initialValues?.nombre ?? '',
      unidad_administrativa_id: initialValues?.unidad_administrativa_id
        ?? ('' as unknown as number),
      codigo:                   initialValues?.codigo ?? '',
      nivel:                    initialValues?.nivel ?? '',
      remuneracion:             initialValues?.remuneracion ?? null,
    },
    validate: zodResolver(puestoSchema),
  })

  const unidadOptions = (unidades as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  return (
    <form id="puesto-form" onSubmit={form.onSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={12}>
          <TextInput
            label="Nombre del puesto"
            placeholder="Ej: Analista de Talento Humano"
            {...contained}
            {...form.getInputProps('nombre')}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Select
            label="Unidad administrativa"
            placeholder="Seleccionar unidad"
            data={unidadOptions}
            searchable
            {...contained}
            value={form.values.unidad_administrativa_id
              ? String(form.values.unidad_administrativa_id) : ''}
            onChange={(v) =>
              form.setFieldValue('unidad_administrativa_id',
                v ? Number(v) : ('' as unknown as number))
            }
            error={form.errors.unidad_administrativa_id}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Código"
            placeholder="Ej: PUESTO-001"
            {...contained}
            {...form.getInputProps('codigo')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Nivel"
            placeholder="Ej: Profesional 4"
            {...contained}
            {...form.getInputProps('nivel')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <NumberInput
            label="Remuneración"
            placeholder="0.00"
            decimalScale={2}
            prefix="$"
            {...contained}
            value={form.values.remuneracion ?? ''}
            onChange={(v) =>
              form.setFieldValue('remuneracion',
                typeof v === 'number' ? v : null)
            }
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
