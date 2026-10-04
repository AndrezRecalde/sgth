'use client'

import { Alert, Select, Stack, TextInput, Textarea } from '@mantine/core'
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
import { vistoBuenoSchema, type VistoBuenoFormValues } from '../schemas/vistoBueno.schema'
import { CAUSAL_LABELS, CAUSAL_NUMERAL } from '../utils/etiquetas'
import type { CausalVistoBueno } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
}

const CAUSAL_OPTIONS = (Object.keys(CAUSAL_LABELS) as CausalVistoBueno[])
  .sort((a, b) => CAUSAL_NUMERAL[a] - CAUSAL_NUMERAL[b])
  .map((c) => ({ value: c, label: `${CAUSAL_NUMERAL[c]}. ${CAUSAL_LABELS[c]}` }))

export function VistoBuenoModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const { crearVistoBueno } = useDisciplinarioMutations()

  const {
    control,
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<VistoBuenoFormValues>({
    // `servidor_id` y `causal` se omiten: los elige quien solicita (regla 09).
    resolver: zodResolver(vistoBuenoSchema),
    defaultValues: {
      hechos: '',
      fecha_solicitud: fromDateValue(new Date()),
      numero_tramite_mdt: '',
      inspectoria: '',
    },
  })

  const cerrar = () => {
    reset()
    onClose()
  }

  const guardar = async (valores: VistoBuenoFormValues) => {
    try {
      await crearVistoBueno.mutateAsync({
        ...valores,
        numero_tramite_mdt: valores.numero_tramite_mdt.trim() || null,
        inspectoria: valores.inspectoria.trim() || null,
      })
      cerrar()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in vistoBuenoSchema.shape) {
          setError(campo as keyof VistoBuenoFormValues, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo solicitar el visto bueno', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Solicitar visto bueno"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Registrar solicitud"
      submitting={crearVistoBueno.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          El visto bueno lo resuelve el Inspector del Trabajo, no la institución.
          Aquí se registra el trámite y, más adelante, la resolución que emita el
          Ministerio. Solo aplica a obreros bajo Código del Trabajo.
        </Alert>

        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Trabajador"
              required
              error={errors.servidor_id?.message}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />

        <Controller
          name="causal"
          control={control}
          render={({ field }) => (
            <Select
              label="Causal (Art. 172 del Código del Trabajo)"
              required
              placeholder="Seleccione la causal invocada"
              data={CAUSAL_OPTIONS}
              searchable
              error={errors.causal?.message}
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />

        <Textarea
          label="Fundamento de hecho"
          required
          placeholder="Relate los hechos que sustentan la solicitud"
          rows={4}
          error={errors.hechos?.message}
          {...contained}
          {...register('hechos')}
        />

        <Controller
          name="fecha_solicitud"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de presentación de la solicitud"
              valueFormat="DD/MM/YYYY"
              error={errors.fecha_solicitud?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />

        <TextInput
          label="Número de trámite del Ministerio del Trabajo"
          description="Opcional: puede registrarse después, al notificarse el trámite."
          placeholder="Ej: MDT-VB-2026-0042"
          error={errors.numero_tramite_mdt?.message}
          {...contained}
          {...register('numero_tramite_mdt')}
        />

        <TextInput
          label="Inspectoría"
          placeholder="Ej: Inspectoría del Trabajo de Esmeraldas"
          error={errors.inspectoria?.message}
          {...contained}
          {...register('inspectoria')}
        />
      </Stack>
    </FormModal>
  )
}
