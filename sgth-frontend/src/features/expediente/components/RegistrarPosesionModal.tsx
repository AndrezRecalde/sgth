'use client'

import { Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha, fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useRegistrarPosesion } from '../hooks/useRegistrarPosesion'
import { crearPosesionSchema, type PosesionFormData } from '../schemas/posesion.schema'
import type { MovimientoPersonal } from '@/types/api'

interface Props {
  opened: boolean
  movimiento: MovimientoPersonal
  onClose: () => void
}

/**
 * Registrar la posesión (fase 6.4): una sola vez, desde el día en que rige. En
 * el ocasional es el día en que se presentó a laborar, sin lugar: su registro
 * del contrato no tiene documento que lo imprima.
 */
export function RegistrarPosesionModal({ opened, movimiento, onClose }: Props) {
  return opened ? <Formulario movimiento={movimiento} onClose={onClose} /> : null
}

function Formulario({ movimiento, onClose }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const registrar = useRegistrarPosesion()
  const esContrato = movimiento.instrumento === 'registro_contrato'

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<PosesionFormData>({
    resolver: zodResolver(crearPosesionSchema(!esContrato)),
    defaultValues: { fecha_posesion: hoyIso(), lugar_posesion: esContrato ? null : 'Esmeraldas' },
  })

  const enviar = async (v: PosesionFormData) => {
    try {
      await registrar.mutateAsync({
        id: Number(movimiento.id),
        fecha_posesion: v.fecha_posesion,
        lugar_posesion: esContrato ? null : v.lugar_posesion?.trim() || null,
      })
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo === 'fecha_posesion' || campo === 'lugar_posesion') setError(campo, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={esContrato ? 'Registrar que se presentó a laborar' : 'Registrar la posesión'}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar"
      submitting={registrar.isPending}
    >
      <Stack gap="sm">
        {movimiento.presentacion_vence_el && (
          <Text size="sm" c="dimmed">
            {esContrato
              ? `Tenía hasta el ${formatFecha(movimiento.presentacion_vence_el)} para presentarse: 24 horas desde el registro del contrato.`
              : `Tenía hasta el ${formatFecha(movimiento.presentacion_vence_el)} para presentarse (Reglamento a la LOSEP, Art. 20).`}
          </Text>
        )}
        <Controller
          name="fecha_posesion"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label={esContrato ? 'Se presentó el' : 'Fecha de la posesión'}
              withAsterisk
              valueFormat="DD/MM/YYYY"
              minDate={toDateValue(movimiento.fecha_efectiva) ?? undefined}
              maxDate={new Date()}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
              error={errors.fecha_posesion?.message}
              {...contained}
            />
          )}
        />
        {!esContrato && (
          <TextInput
            label="Lugar"
            description="Lo imprime el documento, en «Posesión del cargo»."
            withAsterisk
            error={errors.lugar_posesion?.message}
            {...contained}
            {...register('lugar_posesion')}
          />
        )}
      </Stack>
    </FormModal>
  )
}
