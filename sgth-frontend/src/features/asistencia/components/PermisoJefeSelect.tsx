'use client'

import { Select, type ComboboxData } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { PermisoFormData } from './permiso.schema'

interface Props {
  form:       UseFormReturn<PermisoFormData>
  opciones:   ComboboxData
  /** Cuántas personas hay para elegir: con grupos, `opciones.length` cuenta los grupos. */
  cantidad:   number
  textoVacio: string
  /** Talento Humano elige primero la unidad: hasta entonces no hay de quién elegir. */
  sinUnidad?: boolean
}

/**
 * El selector de jefe inmediato de un permiso. Con la opción de dirigirlo a
 * Talento Humano se bloquea: el firmante lo resuelve el backend.
 */
export function PermisoJefeSelect({
  form, opciones, cantidad, textoVacio, sinUnidad = false,
}: Props) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form
  const dirigidoATh = useWatch({ control, name: 'dirigido_a_talento_humano' })

  const placeholder = dirigidoATh
    ? 'Firma el jefe de Talento Humano'
    : sinUnidad
      ? 'Seleccione primero la unidad'
      : cantidad === 0
        ? textoVacio
        : 'Seleccionar jefe'

  return (
    <Controller
      name="jefe_id"
      control={control}
      render={({ field }) => (
        <Select
          label="Jefe inmediato"
          placeholder={placeholder}
          data={opciones}
          searchable
          clearable
          disabled={sinUnidad || dirigidoATh}
          {...contained}
          value={field.value ? String(field.value) : null}
          onChange={(v) => field.onChange(v ? Number(v) : null)}
          error={errors.jefe_id?.message}
        />
      )}
    />
  )
}
