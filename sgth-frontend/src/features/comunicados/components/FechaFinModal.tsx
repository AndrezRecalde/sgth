'use client'

import { Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { useMutacionesComunicado } from '../hooks/useMutacionesComunicado'
import { fechaFinSchema, type FechaFinFormData } from '../schemas/fechaFin.schema'
import type { Comunicado } from '../services/tipos'

interface Props {
  comunicado: Comunicado
  opened: boolean
  onClose: () => void
}

/**
 * La fecha de fin es de lo poco que cambia en lo publicado (regla 1). Sirve
 * para alargarla, para acortarla o para devolver al Portal uno ya vencido.
 */
export function FechaFinModal({ comunicado, opened, onClose }: Props) {
  const contained = useContainedInput()
  const { actualizarPublicacion } = useMutacionesComunicado()

  const { control, handleSubmit, setError, formState: { errors } } = useForm<FechaFinFormData>({
    resolver: zodResolver(fechaFinSchema),
    defaultValues: { publicado_hasta: comunicado.publicado_hasta?.substring(0, 10) ?? '' },
  })

  const guardar = (datos: FechaFinFormData) =>
    actualizarPublicacion.mutateAsync({ id: comunicado.id, cambios: { publicado_hasta: datos.publicado_hasta || null } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['publicado_hasta'], 'No se pudo cambiar la fecha de fin'))

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Fecha de fin"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Guardar fecha"
      submitting={actualizarPublicacion.isPending}
    >
      <Stack gap="md">
        <Text size="sm" c="dimmed">
          Hasta ese día, inclusive, se ve en el inicio del Portal. Después pasa al archivo.
        </Text>
        <Controller
          name="publicado_hasta"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de fin"
              placeholder="Sin fecha de fin"
              valueFormat="DD/MM/YYYY"
              minDate={new Date()}
              clearable
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.publicado_hasta?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
