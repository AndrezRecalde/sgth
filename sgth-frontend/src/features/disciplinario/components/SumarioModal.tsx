'use client'

import { Alert, Stack, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { sumarioSchema, type SumarioFormValues } from '../schemas/sumario.schema'

interface Props {
  opened: boolean
  onClose: () => void
}

export function SumarioModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const { crearSumario } = useDisciplinarioMutations()

  const {
    control,
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<SumarioFormValues>({
    resolver: zodResolver(sumarioSchema),
    // `servidor_id` se omite: lo elige quien abre el sumario (regla 09).
    defaultValues: { motivo: '', fecha_apertura: fromDateValue(new Date()) },
  })

  const cerrar = () => {
    reset()
    onClose()
  }

  const guardar = async (valores: SumarioFormValues) => {
    try {
      await crearSumario.mutateAsync(valores)
      cerrar()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in sumarioSchema.shape) {
          setError(campo as keyof SumarioFormValues, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo abrir el sumario', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Abrir sumario administrativo"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Abrir sumario"
      submitting={crearSumario.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          El sumario administrativo es el procedimiento de la LOSEP. A los obreros
          bajo Código del Trabajo se les tramita un visto bueno ante el Inspector
          del Trabajo.
        </Alert>

        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Servidor sumariado"
              required
              error={errors.servidor_id?.message}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />

        <Controller
          name="fecha_apertura"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de apertura"
              valueFormat="DD/MM/YYYY"
              error={errors.fecha_apertura?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />

        <Textarea
          label="Motivo"
          placeholder="Describa los hechos que motivan la apertura del sumario"
          rows={4}
          error={errors.motivo?.message}
          {...contained}
          {...register('motivo')}
        />
      </Stack>
    </FormModal>
  )
}
