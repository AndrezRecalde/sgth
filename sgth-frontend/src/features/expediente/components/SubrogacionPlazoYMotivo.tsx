'use client'

import { Group, Select, Stack, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFecha, fromDateValue, toDateValue } from '@/lib/fecha'
import { AUSENCIA_LABELS, MOTIVO_LABELS } from '../utils/subrogaciones'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { useFormularioSubrogacion } from '../hooks/useFormularioSubrogacion'
import type { AusenciaDelTitular } from '@/types/api'

type Datos = ReturnType<typeof useFormularioSubrogacion>

const plazoDe = (a: AusenciaDelTitular) =>
  a.hasta ? `del ${formatFecha(a.desde)} al ${formatFecha(a.hasta)}` : `desde el ${formatFecha(a.desde)}, sin fecha de fin`

/**
 * El plazo del acto y lo que lo justifica.
 *
 * En la subrogación, la justificación es la ausencia del titular (fase 5.2): se
 * elige entre las que están en el SGTH —o «otra», con su resolución— y de ella
 * salen el motivo y las fechas, que caben dentro. En el encargo es la vacante,
 * y el fin es opcional: dura hasta la designación del titular.
 */
export function SubrogacionPlazoYMotivo({
  form,
  datos,
}: {
  form: UseFormReturn<SubrogacionFormData>
  datos: Datos
}) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form
  const esEncargo = datos.tipo === 'encargo'
  const esOtra = datos.ausenciaTipo === 'otra'

  const ausenciaOptions = [
    ...datos.ausencias.map((a) => ({ value: `${a.tipo}:${a.id}`, label: `${a.etiqueta} — ${plazoDe(a)}` })),
    { value: 'otra', label: AUSENCIA_LABELS.otra },
  ]

  const valorAusencia = esOtra
    ? 'otra'
    : datos.ausenciaSel ? `${datos.ausenciaSel.tipo}:${datos.ausenciaSel.id}` : null

  const ayudaAusencia = (() => {
    if (!datos.subrogadoId) return undefined
    if (datos.ausenciaSel) {
      return `Motivo: ${MOTIVO_LABELS[datos.ausenciaSel.motivo]}. La subrogación cabe ${plazoDe(datos.ausenciaSel)}.`
    }
    if (esOtra) return 'Una ausencia que no está en el SGTH se respalda con el número de su resolución.'
    if (!datos.cargandoAusencias && datos.ausencias.length === 0) {
      return 'El titular no tiene vacaciones, licencias, reposos ni comisiones vigentes en el SGTH.'
    }
    return undefined
  })()

  // Las fechas no pueden salir de la ausencia elegida: el backend lo rechaza.
  const limite = datos.ausenciaSel
  const minDate = limite ? toDateValue(limite.desde) ?? undefined : undefined
  const maxDate = limite?.hasta ? toDateValue(limite.hasta) ?? undefined : undefined

  return (
    <Stack gap="sm">
      {!esEncargo && (
        <Select
          label="Ausencia del titular"
          // Como el puesto con la unidad: sin titular no hay de quién elegir.
          placeholder={!datos.subrogadoId
            ? 'Seleccione primero el puesto'
            : datos.cargandoAusencias ? 'Cargando ausencias…' : 'Seleccionar la ausencia que la motiva'}
          data={ausenciaOptions}
          disabled={!datos.subrogadoId}
          {...contained}
          value={valorAusencia}
          onChange={datos.elegirAusencia}
          description={ayudaAusencia}
          error={errors.ausencia_tipo?.message ?? errors.ausencia_id?.message}
        />
      )}

      <Group grow>
        <Controller
          name="fecha_inicio"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de inicio"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              minDate={minDate}
              maxDate={maxDate}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_inicio?.message}
            />
          )}
        />
        <Controller
          name="fecha_fin"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label={esEncargo ? 'Fecha de fin (opcional)' : 'Fecha de fin'}
              placeholder={esEncargo ? 'Hasta la designación del titular' : 'Seleccionar fecha'}
              valueFormat="DD/MM/YYYY"
              clearable={esEncargo}
              minDate={minDate}
              maxDate={maxDate}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_fin?.message}
            />
          )}
        />
      </Group>

      <TextInput
        label="Número de resolución"
        placeholder={esOtra ? undefined : 'Opcional'}
        withAsterisk={esOtra}
        {...contained}
        {...register('resolucion_numero')}
        error={errors.resolucion_numero?.message}
      />

      <Textarea
        label="Observación"
        placeholder="Opcional"
        autosize
        minRows={2}
        {...contained}
        {...register('observacion')}
        error={errors.observacion?.message}
      />
    </Stack>
  )
}
