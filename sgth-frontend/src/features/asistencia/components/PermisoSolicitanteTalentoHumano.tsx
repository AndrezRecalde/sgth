'use client'

import { Grid, Select } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { opcionServidor, useOpcionesSolicitante } from '../hooks/useOpcionesSolicitante'
import { PermisoJefeSelect } from './PermisoJefeSelect'
import type { PermisoFormData } from './permiso.schema'

interface Props {
  form:        UseFormReturn<PermisoFormData>
  unidadSelId: number | null
  onUnidad:    (id: number | null) => void
}

/**
 * Talento Humano registra a nombre de cualquier servidor: elige la unidad, el
 * servidor y, entre los jefes de esa unidad, quién firma.
 */
export function PermisoSolicitanteTalentoHumano({ form, unidadSelId, onUnidad }: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const { servidores, opcionesUnidad, opcionesJefe } = useOpcionesSolicitante(unidadSelId)
  const opcionesServidor = servidores.map(opcionServidor)
  const servidorId = useWatch({ control, name: 'servidor_id' })
  // Nadie firma su propio permiso: el jefe de la unidad que pide uno no se
  // ofrece a sí mismo. Su jefe está en otra unidad, así que se dirige a
  // Talento Humano (el backend lo rechaza igual).
  const jefesPosibles = opcionesJefe.filter((o) => o.value !== String(servidorId))

  return (
    <>
      <Controller
        name="unidad_administrativa_id"
        control={control}
        render={({ field }) => (
          <Select
            label="Unidad administrativa"
            placeholder="Seleccionar unidad"
            data={opcionesUnidad}
            searchable
            {...contained}
            value={field.value ? String(field.value) : null}
            onChange={(v) => {
              const id = v ? Number(v) : undefined
              field.onChange(id)
              onUnidad(id ?? null)
              setValue('servidor_id', 0)
              setValue('jefe_id', null)
              setValue('dirigido_a_talento_humano', false)
            }}
            error={errors.unidad_administrativa_id?.message}
          />
        )}
      />

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="servidor_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Servidor"
                placeholder={
                  !unidadSelId
                    ? 'Seleccione primero la unidad'
                    : opcionesServidor.length === 0
                      ? 'Sin servidores en esta unidad'
                      : 'Seleccionar servidor'
                }
                data={opcionesServidor}
                searchable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : undefined)
                  // Si el elegido era el jefe ya marcado, deja de poder firmar.
                  if (v && form.getValues('jefe_id') === Number(v)) {
                    setValue('jefe_id', null)
                  }
                  // La opción de Talento Humano se confirmó para otro
                  // servidor: si el nuevo es el propio jefe de TH ya no
                  // cabe, y en cualquier caso hay que volver a decidirla.
                  setValue('dirigido_a_talento_humano', false)
                }}
                error={errors.servidor_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <PermisoJefeSelect
            form={form}
            opciones={jefesPosibles}
            cantidad={jefesPosibles.length}
            textoVacio="Sin jefes en esta unidad"
            sinUnidad={!unidadSelId}
          />
        </Grid.Col>
      </Grid>
    </>
  )
}
