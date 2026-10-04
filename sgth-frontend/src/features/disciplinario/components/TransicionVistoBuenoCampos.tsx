'use client'

import { Stack, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  Controller,
  type Control,
  type FieldErrors,
  type UseFormRegister,
} from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type { TransicionVistoBuenoFormValues } from '../schemas/transicionVistoBueno.schema'

interface Props {
  control: Control<TransicionVistoBuenoFormValues>
  register: UseFormRegister<TransicionVistoBuenoFormValues>
  errors: FieldErrors<TransicionVistoBuenoFormValues>
  /** Se pasa a `concedido` o `negado`: hay resolución del Inspector. */
  esResolucion: boolean
  /** Se pasa a `notificado`: hay datos del trámite ante el Ministerio. */
  esNotificacion: boolean
  /** Se pasa a `impugnado`: el juicio o la causa y su fecha. */
  esImpugnacion: boolean
}

/**
 * Los campos que pide cada avance del trámite, que son los que
 * `VistoBuenoService::transicionar()` lee según el estado de destino. Pasar a
 * «en investigación» o «desistido» no pide ninguno.
 */
export function TransicionVistoBuenoCampos({
  control,
  register,
  errors,
  esResolucion,
  esNotificacion,
  esImpugnacion,
}: Props) {
  const contained = useContainedInput()

  return (
    <Stack gap="sm">
      {(esResolucion || esNotificacion || esImpugnacion) && (
        <Controller
          name="fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label={esResolucion
                ? 'Fecha de la resolución'
                : esImpugnacion ? 'Fecha de la impugnación' : 'Fecha de notificación'}
              required
              valueFormat="DD/MM/YYYY"
              error={errors.fecha?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />
      )}

      {esNotificacion && (
        <>
          <TextInput
            label="Número de trámite del Ministerio del Trabajo"
            placeholder="Ej: MDT-VB-2026-0042"
            error={errors.numero_tramite_mdt?.message}
            {...contained}
            {...register('numero_tramite_mdt')}
          />
          {/* La inspectoría se capturaba al solicitar y después no había forma
              de corregirla, aunque el API siempre la aceptó aquí. */}
          <TextInput
            label="Inspectoría"
            placeholder="Ej: Inspectoría del Trabajo de Esmeraldas"
            error={errors.inspectoria?.message}
            {...contained}
            {...register('inspectoria')}
          />
          <TextInput
            label="Inspector"
            placeholder="Nombre del Inspector del Trabajo"
            error={errors.inspector_nombre?.message}
            {...contained}
            {...register('inspector_nombre')}
          />
        </>
      )}

      {esImpugnacion && (
        <TextInput
          label="Juicio o causa"
          required
          placeholder="Ej: Juicio 08281-2026-00123, Unidad Judicial de Trabajo"
          error={errors.impugnacion_referencia?.message}
          {...contained}
          {...register('impugnacion_referencia')}
        />
      )}

      {esResolucion && (
        <Textarea
          label="Detalle de la resolución del Inspector"
          required
          placeholder="Transcriba o resuma lo resuelto por el Inspector del Trabajo"
          rows={4}
          error={errors.resolucion_detalle?.message}
          {...contained}
          {...register('resolucion_detalle')}
        />
      )}
    </Stack>
  )
}
