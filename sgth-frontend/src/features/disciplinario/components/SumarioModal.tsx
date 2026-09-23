'use client'

import { useState } from 'react'
import {
  Alert, Stack, Textarea,
} from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { fromDateValueOrNull } from '@/lib/fecha'

interface Props {
  opened: boolean
  onClose: () => void
}

export function SumarioModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const { crearSumario } = useDisciplinarioMutations()

  const [servidorId, setServidorId] = useState<number | null>(null)
  const [motivo, setMotivo] = useState('')
  // El selector de Mantine v9 devuelve una CADENA `YYYY-MM-DD` en cuanto se
  // elige una fecha; solo el valor inicial es un `Date`. El estado admite las
  // dos formas, que es lo que `fromDateValue` sabe leer.
  const [fechaApertura, setFechaApertura] = useState<Date | string | null>(new Date())
  const [error, setError] = useState<string | null>(null)

  const limpiar = () => {
    setServidorId(null)
    setMotivo('')
    setFechaApertura(new Date())
    setError(null)
  }

  const handleClose = () => {
    limpiar()
    onClose()
  }

  const submit = () => {
    if (!servidorId) return setError('Seleccione el servidor sumariado.')
    if (motivo.trim().length < 5) return setError('Describa el motivo del sumario.')

    setError(null)

    crearSumario
      .mutateAsync({
        servidor_id: servidorId,
        motivo: motivo.trim(),
        fecha_apertura: fromDateValueOrNull(fechaApertura),
      })
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="Abrir sumario administrativo"
      size="lg"
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          El sumario administrativo es el procedimiento de la LOSEP. A los obreros
          bajo Código del Trabajo se les tramita un visto bueno ante el Inspector
          del Trabajo.
        </Alert>

        <BuscarServidorSelect
          label="Servidor sumariado"
          value={servidorId}
          onChange={setServidorId}
          required
        />

        <DatePickerInput
          label="Fecha de apertura"
          value={fechaApertura}
          onChange={(v) => setFechaApertura(v)}
          valueFormat="DD/MM/YYYY"
          {...contained}
        />

        <Textarea
          label="Motivo"
          placeholder="Describa los hechos que motivan la apertura del sumario"
          rows={4}
          value={motivo}
          onChange={(e) => setMotivo(e.currentTarget.value)}
          {...contained}
        />

        {error && <Alert variant="light" color="red">{error}</Alert>}
      </Stack>
      <ModalFooter
        onCancel={handleClose}
        submitLabel="Abrir sumario"
        submitting={crearSumario.isPending}
        onSubmit={submit}
      />
    </SgthModal>
  )
}
