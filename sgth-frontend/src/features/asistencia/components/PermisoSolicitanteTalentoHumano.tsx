'use client'

import { Grid, Select } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { opcionServidor, useOpcionesSolicitante } from '../hooks/useOpcionesSolicitante'
import { useJefesDeLaInstitucion } from '../hooks/useJefesDeLaInstitucion'
import { PermisoJefeSelect } from './PermisoJefeSelect'
import type { PermisoFormData } from './permiso.schema'

interface Props {
  form:        UseFormReturn<PermisoFormData>
  unidadSelId: number | null
  onUnidad:    (id: number | null) => void
}

/**
 * Talento Humano registra a nombre de cualquier servidor: elige la unidad, el
 * servidor y quién firma. Primero se ofrecen los jefes de esa unidad y después
 * los de las demás: el superior de un jefe de unidad está fuera de ella, y el
 * jefe de Talento Humano no tenía a nadie que le firmara (decidido con TH el
 * 2026-10-07).
 */
export function PermisoSolicitanteTalentoHumano({ form, unidadSelId, onUnidad }: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const { servidores, opcionesUnidad, opcionesJefe } = useOpcionesSolicitante(unidadSelId)
  const opcionesServidor = servidores.map(opcionServidor)
  const jefesInstitucion = useJefesDeLaInstitucion()
  const servidorId = useWatch({ control, name: 'servidor_id' })

  // Nadie firma su propio permiso: el servidor elegido no se ofrece como su
  // jefe (el backend lo rechaza igual).
  const noEsElServidor = (o: { value: string }) => o.value !== String(servidorId)
  const deLaUnidad = opcionesJefe.filter(noEsElServidor)
  const yaListados = new Set(opcionesJefe.map((o) => o.value))
  const deOtras = jefesInstitucion.filter((o) => noEsElServidor(o) && !yaListados.has(o.value))
  const jefesPosibles = [
    ...(deLaUnidad.length ? [{ group: 'Jefes de la unidad', items: deLaUnidad }] : []),
    ...(deOtras.length ? [{ group: 'Jefes de otras unidades', items: deOtras }] : []),
  ]

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
            cantidad={deLaUnidad.length + deOtras.length}
            textoVacio="Sin jefes en esta unidad"
            sinUnidad={!unidadSelId}
          />
        </Grid.Col>
      </Grid>
    </>
  )
}
