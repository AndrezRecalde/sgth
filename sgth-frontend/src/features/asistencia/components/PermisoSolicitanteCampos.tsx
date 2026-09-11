'use client'

import { Grid, Select, TextInput } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { opcionServidor, useOpcionesSolicitante } from '../hooks/useOpcionesSolicitante'
import { DirigirATalentoHumano } from './DirigirATalentoHumano'
import type { PermisoFormData } from './permiso.schema'

interface Props {
  form:         UseFormReturn<PermisoFormData>
  /** Talento Humano elige unidad y servidor; el resto registra solo el propio. */
  emiteATodos:  boolean
  nombrePropio: string
  unidadSelId:  number | null
  onUnidad:     (id: number | null) => void
}

/**
 * A nombre de quién va el permiso y quién lo firma: unidad, servidor, jefe
 * inmediato y la opción de dirigirlo a Talento Humano.
 */
export function PermisoSolicitanteCampos({
  form, emiteATodos, nombrePropio, unidadSelId, onUnidad,
}: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const dirigidoATh = useWatch({ control, name: 'dirigido_a_talento_humano' })
  const servidorId = useWatch({ control, name: 'servidor_id' })
  const { servidores, opcionesUnidad, opcionesJefe } = useOpcionesSolicitante(unidadSelId)
  const opcionesServidor = servidores.map(opcionServidor)

  // El mismo selector para los dos casos: el jefe se elige igual registre
  // Talento Humano o el propio servidor.
  const selectorJefe = (
    <Controller
      name="jefe_id"
      control={control}
      render={({ field }) => (
        <Select
          label="Jefe inmediato"
          placeholder={
            dirigidoATh
              ? 'Firma el jefe de Talento Humano'
              : !unidadSelId
                ? 'Seleccione primero la unidad'
                : opcionesJefe.length === 0
                  ? 'Sin jefes en esta unidad'
                  : 'Seleccionar jefe'
          }
          data={opcionesJefe}
          searchable
          clearable
          disabled={!unidadSelId || dirigidoATh}
          {...contained}
          value={field.value ? String(field.value) : null}
          onChange={(v) => field.onChange(v ? Number(v) : null)}
          error={errors.jefe_id?.message}
        />
      )}
    />
  )

  return (
    <>
      {emiteATodos ? (
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

            <Grid.Col span={{ base: 12, sm: 6 }}>{selectorJefe}</Grid.Col>
          </Grid>
        </>
      ) : (
        <Grid>
          {/* El solicitante es quien tiene la sesión: no se elige. */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Servidor"
              description="Solo puede registrar sus propios permisos."
              value={nombrePropio}
              readOnly
              {...contained}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, sm: 6 }}>{selectorJefe}</Grid.Col>
        </Grid>
      )}

      {/*
        Omitir al jefe inmediato. Quien firma entonces no se elige: es el
        jefe vigente de la unidad de Talento Humano, o quien lo subrogue, y lo
        resuelve el backend con la misma regla que las Acciones de Personal.
      */}
      <Controller
        name="dirigido_a_talento_humano"
        control={control}
        render={({ field }) => (
          <DirigirATalentoHumano
            activo={field.value}
            servidorId={servidorId || undefined}
            onCambiar={(activo) => {
              field.onChange(activo)
              if (activo) setValue('jefe_id', null)
            }}
          />
        )}
      />
    </>
  )
}
