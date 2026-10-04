'use client'

import { Grid, Select, Switch, TextInput } from '@mantine/core'
import { Controller, useFormContext, useWatch } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useProvincias } from '../hooks/useProvincias'
import { useCantones } from '../hooks/useCantones'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { Provincia, Canton } from '@/types/api'

/**
 * Dónde nació: provincia y cantón, o nacionalidad y país si es extranjero.
 * Va dentro del `Grid` de ServidorFormPersonal, del que se separó para que
 * aquel no pasara de 200 líneas (regla 02).
 *
 * Vaciar la provincia o el cantón marca el campo como modificado: el modal de
 * edición solo envía lo que se tocó, y sin eso el vacío no viajaba.
 */
export function ServidorFormOrigen() {
  const contained = useContainedInput()
  const { control, register, setValue, formState: { errors } } =
    useFormContext<ServidorBasicoFormData>()

  const esExtranjero = useWatch({ control, name: 'es_extranjero' })
  const provinciaId  = useWatch({ control, name: 'provincia_nacimiento_id' })

  const { data: provincias = [] } = useProvincias()
  const { data: cantones = [] } = useCantones(esExtranjero ? null : (provinciaId ?? null))

  const provinciaOptions = (provincias as Provincia[]).map((p) => ({
    value: String(p.id),
    label: p.nombre ?? `Provincia ${p.id}`,
  }))

  const cantonOptions = (cantones as Canton[]).map((c) => ({
    value: String(c.id),
    label: (c as Canton & { nombre?: string }).nombre ?? `Cantón ${c.id}`,
  }))

  return (
    <>
      <Grid.Col span={12}>
        <SectionHeading title="Origen" mt="xs" mb="xs" />
      </Grid.Col>
      <Grid.Col span={12}>
        <Controller
          name="es_extranjero"
          control={control}
          render={({ field }) => (
            <Switch
              label="¿Es extranjero?"
              checked={field.value}
              onChange={(e) => {
                field.onChange(e.currentTarget.checked)
                setValue('provincia_nacimiento_id', null, { shouldDirty: true })
                setValue('canton_nacimiento_id', null, { shouldDirty: true })
              }}
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
              control={control}
              render={({ field }) => (
                <Select
                  label="Provincia de nacimiento"
                  placeholder="Seleccionar provincia"
                  data={provinciaOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => {
                    field.onChange(v ? Number(v) : null)
                    setValue('canton_nacimiento_id', null, { shouldDirty: true })
                  }}
                  error={errors.provincia_nacimiento_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="canton_nacimiento_id"
              control={control}
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
    </>
  )
}
