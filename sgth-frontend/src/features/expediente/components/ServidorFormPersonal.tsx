'use client'

import {
  TextInput, Select, Grid, Switch, Text,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
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

const TIPO_SANGRE_OPTIONS = [
  'A+','A-','B+','B-','AB+','AB-','O+','O-',
].map(v => ({ value: v, label: v }))

interface Props {
  form: UseFormReturnType<ServidorFormData>
}

export function ServidorFormPersonal({ form }: Props) {
  const contained   = useContainedInput()
  const esExtranjero = form.values.es_extranjero

  const { data: provincias = [] } = useProvincias()
  const provinciaId = form.values.provincia_nacimiento_id
  const { data: cantones = [] } = useCantones(
    esExtranjero ? null : (provinciaId ?? null)
  )

  const provinciaOptions = (provincias as Provincia[]).map(p => ({
    value: String(p.id),
    label: p.nombre ?? `Provincia ${p.id}`,
  }))

  const cantonOptions = (cantones as Canton[]).map(c => ({
    value: String(c.id),
    label: (c as Canton & { nombre?: string }).nombre ?? `Cantón ${c.id}`,
  }))

  const toDate = (v?: string | null) =>
    v ? new Date(v) : null

  const fromDate = (d: any) => {
    if (!d) return null
    const date = new Date(d)
    return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0]
  }

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer nombre"
          placeholder="Primer nombre"
          {...contained}
          {...form.getInputProps('nombre')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo nombre"
          placeholder="Segundo nombre (opcional)"
          {...contained}
          {...form.getInputProps('segundo_nombre')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer apellido"
          placeholder="Primer apellido"
          {...contained}
          {...form.getInputProps('apellido')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo apellido"
          placeholder="Segundo apellido (opcional)"
          {...contained}
          {...form.getInputProps('segundo_apellido')}
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
        <DatePickerInput
          label="Fecha de nacimiento"
          placeholder="Seleccionar fecha"
          maxDate={new Date()}
          valueFormat="YYYY-MM-DD"
          {...contained}
          value={toDate(form.values.fecha_nacimiento)}
          onChange={(date) =>
            form.setFieldValue('fecha_nacimiento',
              fromDate(date) ?? '')
          }
          error={form.errors.fecha_nacimiento}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Select
          label="Tipo de sangre"
          placeholder="Seleccionar (opcional)"
          data={TIPO_SANGRE_OPTIONS}
          clearable
          {...contained}
          value={form.values.tipo_sangre ?? ''}
          onChange={(v) => form.setFieldValue('tipo_sangre', (v ?? null) as ServidorFormData['tipo_sangre'])}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <Switch
          label="¿Es extranjero?"
          checked={form.values.es_extranjero}
          onChange={(e) => {
            form.setFieldValue('es_extranjero', e.currentTarget.checked)
            form.setFieldValue('provincia_nacimiento_id', null)
            form.setFieldValue('canton_nacimiento_id', null)
          }}
          color="emerald"
          mt="xs"
        />
      </Grid.Col>

      {!esExtranjero ? (
        <>
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
                  v ? Number(v) : null)
                form.setFieldValue('canton_nacimiento_id', null)
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
                  v ? Number(v) : null)
              }
              error={form.errors.canton_nacimiento_id}
            />
          </Grid.Col>
        </>
      ) : (
        <>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Nacionalidad"
              placeholder="Ej: Colombiana"
              {...contained}
              {...form.getInputProps('nacionalidad')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="País de origen"
              placeholder="Ej: Colombia"
              {...contained}
              {...form.getInputProps('pais_origen')}
            />
          </Grid.Col>
        </>
      )}

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Switch
          label="¿Tiene discapacidad?"
          checked={form.values.tiene_discapacidad}
          onChange={(e) =>
            form.setFieldValue('tiene_discapacidad', e.currentTarget.checked)
          }
          color="emerald"
          mt="xs"
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Switch
          label="¿Tiene enfermedad catastrófica?"
          checked={form.values.tiene_enfermedad_catastrofica}
          onChange={(e) =>
            form.setFieldValue('tiene_enfermedad_catastrofica',
              e.currentTarget.checked)
          }
          color="emerald"
          mt="xs"
        />
      </Grid.Col>
    </Grid>
  )
}
