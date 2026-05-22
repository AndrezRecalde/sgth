'use client'

import { TextInput, Select, Textarea, Switch, Grid } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
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
  const { data: unidades = [] } = useUnidades({ nivel: 2 })

  const form = useForm<ExtensionFormData>({
    initialValues: {
      unidad_administrativa_id: initialValues?.unidad_administrativa_id
        ?? ('' as unknown as number),
      numero_extension: initialValues?.numero_extension ?? '',
      responsable:      initialValues?.responsable ?? '',
      descripcion:      initialValues?.descripcion ?? '',
      estado:           initialValues?.estado ?? true,
    },
    validate: zodResolver(extensionSchema),
  })

  const unidadOptions = (unidades as unknown as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  return (
    <form id="extension-form" onSubmit={form.onSubmit(onSubmit)}>
      <Grid>
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
            label="Número de extensión"
            placeholder="Ej: 1234"
            maxLength={10}
            {...contained}
            {...form.getInputProps('numero_extension')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Responsable"
            placeholder="Nombre del responsable"
            {...contained}
            {...form.getInputProps('responsable')}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Descripción"
            placeholder="Descripción opcional"
            rows={2}
            {...contained}
            {...form.getInputProps('descripcion')}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Switch
            label="Extensión activa"
            checked={form.values.estado}
            onChange={(e) =>
              form.setFieldValue('estado', e.currentTarget.checked)
            }
            color="emerald"
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
