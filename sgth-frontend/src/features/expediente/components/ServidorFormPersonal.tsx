'use client'

import { TextInput, Select, Grid } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useProvincias } from '../hooks/useProvincias'
import { useCantones } from '../hooks/useCantones'
import type { UseFormReturnType } from '@mantine/form'
import type { ServidorFormData } from '../schemas/servidor.schema'
import type { Provincia, Canton } from '@/types/api'

const GENERO_OPTIONS = [
  { value: 'masculino', label: 'Masculino' },
  { value: 'femenino',  label: 'Femenino' },
  { value: 'otro',      label: 'Otro' },
]

const ESTADO_CIVIL_OPTIONS = [
  { value: 'soltero',     label: 'Soltero/a' },
  { value: 'casado',      label: 'Casado/a' },
  { value: 'divorciado',  label: 'Divorciado/a' },
  { value: 'viudo',       label: 'Viudo/a' },
  { value: 'union_libre', label: 'Unión libre' },
]

interface Props {
  form: UseFormReturnType<ServidorFormData>
}

export function ServidorFormPersonal({ form }: Props) {
  const contained = useContainedInput()
  const { data: provincias = [] } = useProvincias()
  const provinciaId = form.values.provincia_nacimiento_id
  const { data: cantones = [] } = useCantones(provinciaId ?? null)

  const provinciaOptions = (provincias as Provincia[]).map(p => ({
    value: String(p.id),
    label: p.nombre ?? `Provincia ${p.id}`,
  }))

  const cantonOptions = (cantones as Canton[]).map(c => ({
    value: String(c.id),
    label: (c as Canton & { nombre?: string }).nombre ?? `Cantón ${c.id}`,
  }))

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Nombres"
          placeholder="Nombres del servidor"
          {...contained}
          {...form.getInputProps('nombres')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Apellidos"
          placeholder="Apellidos del servidor"
          {...contained}
          {...form.getInputProps('apellidos')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <TextInput
          label="Cédula de identidad"
          placeholder="0000000000"
          maxLength={10}
          {...contained}
          {...form.getInputProps('cedula')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Select
          label="Género"
          placeholder="Seleccionar"
          data={GENERO_OPTIONS}
          {...contained}
          value={form.values.genero ?? ''}
          onChange={(v) => form.setFieldValue('genero',
            v as ServidorFormData['genero'])}
          error={form.errors.genero}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Select
          label="Estado civil"
          placeholder="Seleccionar"
          data={ESTADO_CIVIL_OPTIONS}
          {...contained}
          value={form.values.estado_civil ?? ''}
          onChange={(v) => form.setFieldValue('estado_civil',
            v as ServidorFormData['estado_civil'])}
          error={form.errors.estado_civil}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Fecha de nacimiento"
          placeholder="YYYY-MM-DD"
          {...contained}
          {...form.getInputProps('fecha_nacimiento')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Provincia de nacimiento"
          placeholder="Seleccionar provincia"
          data={provinciaOptions}
          searchable
          {...contained}
          value={form.values.provincia_nacimiento_id
            ? String(form.values.provincia_nacimiento_id) : ''}
          onChange={(v) => {
            form.setFieldValue('provincia_nacimiento_id',
              v ? Number(v) : ('' as unknown as number))
            form.setFieldValue('canton_nacimiento_id',
              '' as unknown as number)
          }}
          error={form.errors.provincia_nacimiento_id}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Cantón de nacimiento"
          placeholder="Seleccionar cantón"
          data={cantonOptions}
          searchable
          disabled={!provinciaId}
          {...contained}
          value={form.values.canton_nacimiento_id
            ? String(form.values.canton_nacimiento_id) : ''}
          onChange={(v) =>
            form.setFieldValue('canton_nacimiento_id',
              v ? Number(v) : ('' as unknown as number))
          }
          error={form.errors.canton_nacimiento_id}
        />
      </Grid.Col>
    </Grid>
  )
}
