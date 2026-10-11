'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha, fromDateValue, sumarDias, toDateValue } from '@/lib/fecha'
import { useFijarFinDelPeriodo } from '../hooks/useAutoridadesElectas'
import { finDelPeriodoSchema, type FinDelPeriodoFormData } from '../schemas/finDelPeriodo.schema'
import type { AutoridadElecta } from '../services/autoridadElectaService'

interface Props {
  opened: boolean
  onClose: () => void
  autoridad: AutoridadElecta | null
}

/**
 * La autoridad que ya estaba cuando el sistema empezó a contar su período no
 * tiene fecha de fin (fase 4.1). Talento Humano la fija aquí; queda en la
 * bitácora del vínculo, y ese día el período se cierra solo.
 */
export function FinDelPeriodoModal({ opened, onClose, autoridad }: Props) {
  if (!autoridad) {
    return <SgthModal opened={opened} onClose={onClose} title="Fijar el fin del período" />
  }

  return <FormularioFinDelPeriodo key={autoridad.contrato_id} opened={opened} onClose={onClose} autoridad={autoridad} />
}

function FormularioFinDelPeriodo({
  opened,
  onClose,
  autoridad,
}: {
  opened: boolean
  onClose: () => void
  autoridad: AutoridadElecta
}) {
  const contained = useContainedInput()
  const fijar = useFijarFinDelPeriodo()

  const {
    control,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FinDelPeriodoFormData>({
    resolver: zodResolver(finDelPeriodoSchema),
    defaultValues: { fecha_fin: '' },
  })

  const guardar = async (valores: FinDelPeriodoFormData) => {
    try {
      await fijar.mutateAsync({ contratoId: autoridad.contrato_id, fechaFin: valores.fecha_fin })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (campos?.fecha_fin) setError('fecha_fin', { type: 'server', message: campos.fecha_fin })
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Fijar el fin del período"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Fijar"
      submitting={fijar.isPending}
      size="md"
    >
      <Stack gap="md">
        <div>
          <Text size="sm" fw={600}>{autoridad.servidor.nombre || '—'}</Text>
          <Text size="xs" c="dimmed">
            {autoridad.dignidad ?? 'Autoridad electa'} · desde el {formatFecha(autoridad.desde)}
          </Text>
        </div>

        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          El período dura hasta cuatro años. Ese día el vínculo se cierra solo, y
          si la autoridad fue reelegida, el período nuevo empieza al día siguiente.
        </Alert>

        <Controller
          name="fecha_fin"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Último día del período"
              required
              valueFormat="DD/MM/YYYY"
              minDate={toDateValue(autoridad.desde ? sumarDias(autoridad.desde, 1) : null) ?? undefined}
              // El calendario se abre sobre un modal: hay que levantarlo por
              // encima de la pila de capas que ya hay.
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              error={errors.fecha_fin?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
