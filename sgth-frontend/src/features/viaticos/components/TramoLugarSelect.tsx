'use client'

import { Grid, Select, TextInput } from '@mantine/core'
import { Controller, type Control,
         type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { TramoFormData } from '../schemas/viatico.schema'

type Opcion = { value: string; label: string }

interface Props {
  prefijo:          'origen' | 'destino'
  label:            string
  control:          Control<TramoFormData>
  errors:           FieldErrors<TramoFormData>
  tipo:             'nacional' | 'internacional'
  provinciaOptions: Opcion[]
  cantonOptions:    Opcion[]
  paises:           Opcion[]
  onTipoChange:     (v: string) => void
  onProvinciaChange: (v: string | null) => void
}

export function TramoLugarSelect({
  prefijo,
  label,
  control,
  errors,
  tipo,
  provinciaOptions,
  cantonOptions,
  paises,
  onTipoChange,
  onProvinciaChange,
}: Props) {
  const contained = useContainedInput()

  const tipoKey      = `${prefijo}_tipo`      as const
  const provKey      = `${prefijo}_provincia_id` as const
  const cantonKey    = `${prefijo}_canton_id`  as const
  const paisKey      = `${prefijo}_pais`       as const
  const ciudadKey    = `${prefijo}_ciudad`     as const

  return (
    <>
      <Controller
        name={tipoKey as keyof TramoFormData}
        control={control}
        render={({ field }) => (
          <Select
            label={`Tipo de ${label.toLowerCase()}`}
            data={[
              { value: 'nacional',        label: 'Nacional' },
              { value: 'internacional',   label: 'Internacional' },
            ]}
            {...contained}
            value={field.value as string}
            onChange={(v) => {
              field.onChange(v ?? 'nacional')
              onTipoChange(v ?? 'nacional')
            }}
          />
        )}
      />

      {tipo === 'nacional' ? (
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={provKey as keyof TramoFormData}
              control={control}
              render={({ field }) => (
                <Select
                  label={`Provincia de ${label.toLowerCase()}`}
                  placeholder="Seleccionar"
                  data={provinciaOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => {
                    field.onChange(v ? Number(v) : null)
                    onProvinciaChange(v)
                  }}
                  error={
                    (errors as Record<string, { message?: string }>)[provKey]
                      ?.message
                  }
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={cantonKey as keyof TramoFormData}
              control={control}
              render={({ field }) => (
                <Select
                  label={`Cantón de ${label.toLowerCase()}`}
                  placeholder="Seleccionar"
                  data={cantonOptions}
                  searchable
                  disabled={cantonOptions.length === 0}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) =>
                    field.onChange(v ? Number(v) : null)
                  }
                  error={
                    (errors as Record<string, { message?: string }>)
                      [cantonKey]?.message
                  }
                />
              )}
            />
          </Grid.Col>
        </Grid>
      ) : (
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={paisKey as keyof TramoFormData}
              control={control}
              render={({ field }) => (
                <Select
                  label="País"
                  placeholder="Seleccionar"
                  data={paises}
                  searchable
                  {...contained}
                  value={field.value as string ?? null}
                  onChange={(v) => field.onChange(v ?? null)}
                  error={
                    (errors as Record<string, { message?: string }>)
                      [paisKey]?.message
                  }
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={ciudadKey as keyof TramoFormData}
              control={control}
              render={({ field }) => (
                <TextInput
                  label="Ciudad"
                  placeholder="Ej: Bogotá"
                  {...contained}
                  value={field.value as string ?? ''}
                  onChange={(e) =>
                    field.onChange(e.currentTarget.value)
                  }
                  error={
                    (errors as Record<string, { message?: string }>)
                      [ciudadKey]?.message
                  }
                />
              )}
            />
          </Grid.Col>
        </Grid>
      )}
    </>
  )
}
