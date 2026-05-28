'use client'

import {
  TextInput, Select, Grid, Switch,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useProvincias } from '../hooks/useProvincias'
import { useCantones } from '../hooks/useCantones'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
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
  form: UseFormReturn<ServidorBasicoFormData>
}

export function ServidorFormPersonal({ form }: Props) {
  const contained = useContainedInput()
  const { register, setValue, watch, formState: { errors } } = form

  const esExtranjero = watch('es_extranjero')
  const provinciaId  = watch('provincia_nacimiento_id')

  const { data: provincias = [] } = useProvincias()
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

  const fromDate = (d: Date | string | null) => {
    if (!d) return null
    if (typeof d === 'string') return d
    return d.toISOString().split('T')[0]
  }

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer nombre"
          placeholder="Primer nombre"
          {...contained}
          {...register('nombre')}
          error={errors.nombre?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo nombre"
          placeholder="Segundo nombre (opcional)"
          {...contained}
          {...register('segundo_nombre')}
          error={errors.segundo_nombre?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer apellido"
          placeholder="Primer apellido"
          {...contained}
          {...register('apellido')}
          error={errors.apellido?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo apellido"
          placeholder="Segundo apellido (opcional)"
          {...contained}
          {...register('segundo_apellido')}
          error={errors.segundo_apellido?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <TextInput
          label="Cédula de identidad"
          placeholder="0000000000"
          maxLength={10}
          {...contained}
          {...register('cedula')}
          error={errors.cedula?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="genero"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Género"
              placeholder="Seleccionar"
              data={GENERO_OPTIONS}
              {...contained}
              value={field.value}
              onChange={field.onChange}
              error={errors.genero?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="estado_civil"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Estado civil"
              placeholder="Seleccionar"
              data={ESTADO_CIVIL_OPTIONS}
              {...contained}
              value={field.value}
              onChange={field.onChange}
              error={errors.estado_civil?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_nacimiento"
          control={form.control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de nacimiento"
              placeholder="Seleccionar fecha"
              maxDate={new Date()}
              valueFormat="YYYY-MM-DD"
              {...contained}
              value={toDate(field.value)}
              onChange={(date) => field.onChange(fromDate(date) ?? '')}
              error={errors.fecha_nacimiento?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tipo_sangre"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Tipo de sangre"
              placeholder="Seleccionar (opcional)"
              data={TIPO_SANGRE_OPTIONS}
              clearable
              {...contained}
              value={field.value ?? ''}
              onChange={field.onChange}
              error={errors.tipo_sangre?.message}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <Controller
          name="es_extranjero"
          control={form.control}
          render={({ field }) => (
            <Switch
              label="¿Es extranjero?"
              checked={field.value}
              onChange={(e) => {
                field.onChange(e.currentTarget.checked)
                setValue('provincia_nacimiento_id', null)
                setValue('canton_nacimiento_id', null)
              }}
              color="emerald"
              mt="xs"
            />
          )}
        />
      </Grid.Col>

      {!esExtranjero ? (
        <>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="provincia_nacimiento_id"
              control={form.control}
              render={({ field }) => (
                <Select
                  label="Provincia de nacimiento"
                  placeholder="Seleccionar provincia"
                  data={provinciaOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => {
                    const id = v ? Number(v) : null
                    field.onChange(id)
                    setValue('canton_nacimiento_id', null)
                  }}
                  error={errors.provincia_nacimiento_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="canton_nacimiento_id"
              control={form.control}
              render={({ field }) => (
                <Select
                  label="Cantón de nacimiento"
                  placeholder="Seleccionar cantón"
                  data={cantonOptions}
                  searchable
                  disabled={!provinciaId}
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                  error={errors.canton_nacimiento_id?.message}
                />
              )}
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
              {...register('nacionalidad')}
              error={errors.nacionalidad?.message}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="País de origen"
              placeholder="Ej: Colombia"
              {...contained}
              {...register('pais_origen')}
              error={errors.pais_origen?.message}
            />
          </Grid.Col>
        </>
      )}

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tiene_discapacidad"
          control={form.control}
          render={({ field }) => (
            <Switch
              label="¿Tiene discapacidad?"
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              color="emerald"
              mt="xs"
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tiene_enfermedad_catastrofica"
          control={form.control}
          render={({ field }) => (
            <Switch
              label="¿Tiene enfermedad catastrófica?"
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              color="emerald"
              mt="xs"
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
