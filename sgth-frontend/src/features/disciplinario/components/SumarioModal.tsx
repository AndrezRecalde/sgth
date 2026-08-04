'use client'

import { useState } from 'react'
import {
  Alert, Button, Group, Modal, Stack, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'

interface Props {
  opened: boolean
  onClose: () => void
}

export function SumarioModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crearSumario } = useDisciplinarioMutations()

  const [servidorId, setServidorId] = useState<number | null>(null)
  const [motivo, setMotivo] = useState('')
  const [fechaApertura, setFechaApertura] = useState<Date | null>(new Date())
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

  const toIso = (d: Date | null) =>
    d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : null

  const submit = () => {
    if (!servidorId) return setError('Seleccione el servidor sumariado.')
    if (motivo.trim().length < 5) return setError('Describa el motivo del sumario.')

    setError(null)

    crearSumario
      .mutateAsync({
        servidor_id: servidorId,
        motivo: motivo.trim(),
        fecha_apertura: toIso(fechaApertura),
      })
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Abrir sumario administrativo"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="sm">
        <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />}>
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
          onChange={(v) => setFechaApertura(v as Date | null)}
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

        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={handleClose}>Cancelar</Button>
          <Button color="emerald" loading={crearSumario.isPending} onClick={submit}>
            Abrir sumario
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
