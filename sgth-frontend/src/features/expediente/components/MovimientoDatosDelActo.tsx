'use client'

import { Alert, Group, Switch, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import type { ClaseDelCatalogo } from '@/types/api'
import { FirmantesPanel } from './FirmantesPanel'
import { fromDateValue, fromDateValueOrNull, toDateValue } from '@/lib/fecha'

/**
 * Lo que toda acción de personal declara, sea del tipo que sea: por qué, desde
 * cuándo rige, con qué resolución se respalda, si exige ficha de salud
 * ocupacional y si el puesto pide caución.
 *
 * El período aparte —«desde» y «hasta»— solo en la clase que lo pide —hoy las
 * comisiones de servicios, que es lo único que las define: el servidor no se
 * mueve de puesto—.
 */
export function MovimientoDatosDelActo({
  form,
  clase,
}: {
  form: UseFormReturn<MovimientoFormData>
  /** La clase elegida: de su ficha del catálogo sale si pide período y su aviso. */
  clase?: ClaseDelCatalogo
}) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  const caucionado = useWatch({ control, name: 'caucionado' })

  const muestraFechas = !!clase?.pide_periodo

  return (
    <>
      <Textarea
        label="Explicación"
        placeholder="Detalle y justificación de la acción de personal"
        autosize
        minRows={3}
        {...contained}
        {...register('descripcion')}
        error={errors.descripcion?.message}
      />

      <Controller
        name="fecha_efectiva"
        control={control}
        render={({ field }) => (
          <DatePickerInput
            label="Rige a partir de"
            placeholder="Seleccionar fecha"
            valueFormat="DD/MM/YYYY"
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValue(d))}
            error={errors.fecha_efectiva?.message}
            {...contained}
          />
        )}
      />

      {muestraFechas && (
        <>
          {clase?.aviso && (
            <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
              {clase.aviso}
            </Alert>
          )}
          <Group grow>
            <Controller
              name="fecha_inicio"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Desde"
                  valueFormat="DD/MM/YYYY"
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                  error={errors.fecha_inicio?.message}
                  {...contained}
                />
              )}
            />
            <Controller
              name="fecha_fin"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Hasta"
                  valueFormat="DD/MM/YYYY"
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                  error={errors.fecha_fin?.message}
                  {...contained}
                />
              )}
            />
          </Group>
        </>
      )}

      <TextInput
        label="Número de resolución"
        placeholder="Opcional"
        error={errors.resolucion_numero?.message}
        {...contained}
        {...register('resolucion_numero')}
      />

      <Controller
        name="requiere_dictamen_medico"
        control={control}
        render={({ field }) => (
          <Switch
            label="Requiere ficha de salud ocupacional"
            description="Si se marca, no podrá registrarse sin dictamen de aptitud del dispensario."
            checked={!!field.value}
            onChange={(e) => field.onChange(e.currentTarget.checked)}
          />
        )}
      />

      <SectionHeading title="Firmarán este documento" mt={4} mb={4} />

      <Text size="xs" c="dimmed">
        Se toman del organigrama y quedan sellados al suscribir la acción.
      </Text>
      <FirmantesPanel compacto />

      <SectionHeading title="Caución" mt={4} mb={4} />

      <Controller
        name="caucionado"
        control={control}
        render={({ field }) => (
          <Switch
            label="El puesto exige caución"
            checked={!!field.value}
            onChange={(e) => field.onChange(e.currentTarget.checked)}
          />
        )}
      />

      {caucionado && (
        <Group grow>
          <TextInput
            label="Caución registrada con No."
            {...contained}
            {...register('caucion_numero')}
            error={errors.caucion_numero?.message}
          />
          <Controller
            name="caucion_fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                valueFormat="DD/MM/YYYY"
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                error={errors.caucion_fecha?.message}
                {...contained}
              />
            )}
          />
        </Group>
      )}

      <Textarea
        label="Observación"
        placeholder="Opcional"
        autosize
        minRows={2}
        error={errors.observacion?.message}
        {...contained}
        {...register('observacion')}
      />
    </>
  )
}
