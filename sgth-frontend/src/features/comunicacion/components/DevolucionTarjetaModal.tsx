'use client'

import { Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useMutacionesCredencial } from '../hooks/useCredenciales'
import { devolucionTarjetaSchema, type DevolucionTarjetaFormData } from '../schemas/credencial.schema'
import type { Credencial } from '../services/tiposCredencial'

interface Props {
  credencial: Credencial | null
  nombre?: string
  onClose: () => void
}

/**
 * La tarjeta impresa volvió a Comunicación (docs/diseno-comunicacion-social.md,
 * 6.5). Cierra el paso «Devolución de la credencial» de la desvinculación.
 */
export function DevolucionTarjetaModal({ credencial, nombre, onClose }: Props) {
  const contained = useContainedInput()
  const { registrarDevolucion } = useMutacionesCredencial()

  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<DevolucionTarjetaFormData>({
    resolver: zodResolver(devolucionTarjetaSchema),
    defaultValues: { fecha: hoyIso(), nota: '' },
  })

  const cerrar = () => {
    reset({ fecha: hoyIso(), nota: '' })
    onClose()
  }

  const enviar = (valores: DevolucionTarjetaFormData) => {
    if (!credencial) return

    registrarDevolucion.mutateAsync({ credencialId: credencial.id, fecha: valores.fecha, nota: valores.nota || null })
      .then(cerrar)
      .catch((error) => erroresAlFormulario(error, setError, ['fecha', 'nota'], null))
  }

  return (
    <FormModal
      opened={!!credencial}
      onClose={cerrar}
      title="Recibir la tarjeta"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar la devolución"
      submitting={registrarDevolucion.isPending}
      size="md"
    >
      <Stack gap="md">
        <Text size="sm" c="dimmed">
          {nombre ? `${nombre} entregó` : 'El servidor entregó'} la tarjeta impresa de esta credencial. Si está saliendo
          de la institución, esto cierra el paso «Devolución de la credencial» de su desvinculación.
        </Text>

        <Controller
          name="fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de la devolución"
              valueFormat="DD/MM/YYYY"
              maxDate={new Date()}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d ?? null) ?? '')}
              error={errors.fecha?.message}
            />
          )}
        />

        <Textarea
          label="Nota"
          placeholder="Opcional: quién la recibió, en qué estado llegó…"
          autosize
          minRows={2}
          {...contained}
          {...register('nota')}
          error={errors.nota?.message}
        />
      </Stack>
    </FormModal>
  )
}
