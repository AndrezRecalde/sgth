'use client'

import { Grid, Select } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { generaVacaciones } from '@/lib/regimen'
import { opcionServidor, useOpcionesSolicitante } from '../hooks/useOpcionesSolicitante'
import { SaldoVacacionesAlert } from './SaldoVacacionesAlert'
import { MOTIVO_OPCIONES } from './vacaciones.constants'
import type { VacacionFormData } from './vacacion.schema'

interface Props {
  form:          UseFormReturn<VacacionFormData>
  unidadSelId:   number | null
  servidorSelId: number | null
  onUnidad:      (id: number | null) => void
  onServidor:    (id: number | null) => void
}

/**
 * Quién pide las vacaciones y por qué: unidad, servidor, jefe, reemplazo y
 * motivo, con el saldo del servidor en cuanto se lo elige.
 */
export function VacacionSolicitanteCampos({
  form, unidadSelId, servidorSelId, onUnidad, onServidor,
}: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const motivo = useWatch({ control, name: 'motivo' })
  const { servidores, opcionesUnidad, opcionesJefe } = useOpcionesSolicitante(unidadSelId)

  // Quien no genera vacaciones no aparece en el selector. El backend lo
  // rechaza igual, pero ofrecerlo aquí sería abrir una puerta para cerrarla en
  // la cara: la persona elige, llena las fechas y recién al enviar se entera.
  const opcionesServidor = servidores
    .filter(s => generaVacaciones(s.regimen_laboral))
    .map(opcionServidor)

  // Nadie se reemplaza a sí mismo: el backend lo rechaza, así que tampoco se
  // ofrece.
  const opcionesReemplazo = servidores
    .filter(s => Number(s.id) !== servidorSelId)
    .map(opcionServidor)

  const placeholder = (texto: string) =>
    unidadSelId ? texto : 'Seleccione primero la unidad'

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
              setValue('persona_reemplaza_id', null)
              onServidor(null)
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
                placeholder={placeholder('Seleccionar servidor')}
                data={opcionesServidor}
                searchable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  const id = v ? Number(v) : undefined
                  field.onChange(id)
                  onServidor(id ?? null)
                }}
                error={errors.servidor_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="jefe_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Jefe inmediato"
                placeholder={placeholder('Seleccionar jefe')}
                data={opcionesJefe}
                searchable
                clearable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.jefe_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="persona_reemplaza_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Persona que reemplaza"
                placeholder="Seleccionar (opcional)"
                data={opcionesReemplazo}
                searchable
                clearable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="motivo"
            control={control}
            render={({ field }) => (
              <Select
                label="Motivo"
                placeholder="Seleccionar motivo"
                data={MOTIVO_OPCIONES}
                searchable
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.motivo?.message}
              />
            )}
          />
        </Grid.Col>
      </Grid>

      {servidorSelId && <SaldoVacacionesAlert servidorId={servidorSelId} motivo={motivo} />}
    </>
  )
}
