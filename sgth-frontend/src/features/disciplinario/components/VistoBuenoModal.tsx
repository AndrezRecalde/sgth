'use client'

import { useState } from 'react'
import {
  Alert, Button, Group, Modal, Select, Stack, TextInput, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
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
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crearVistoBueno } = useDisciplinarioMutations()

  const [servidorId, setServidorId] = useState<number | null>(null)
  const [causal, setCausal] = useState<CausalVistoBueno | null>(null)
  const [hechos, setHechos] = useState('')
  const [fechaSolicitud, setFechaSolicitud] = useState<Date | null>(new Date())
  const [numeroTramite, setNumeroTramite] = useState('')
  const [inspectoria, setInspectoria] = useState('')
  const [error, setError] = useState<string | null>(null)

  const limpiar = () => {
    setServidorId(null)
    setCausal(null)
    setHechos('')
    setFechaSolicitud(new Date())
    setNumeroTramite('')
    setInspectoria('')
    setError(null)
  }

  const handleClose = () => {
    limpiar()
    onClose()
  }

  const toIso = (d: Date | null) =>
    d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : null

  const submit = () => {
    if (!servidorId) return setError('Seleccione al trabajador.')
    if (!causal) return setError('Indique la causal del Art. 172.')
    if (hechos.trim().length < 5) return setError('Relate el fundamento de hecho de la solicitud.')
    if (!fechaSolicitud) return setError('Indique la fecha de la solicitud.')

    setError(null)

    crearVistoBueno
      .mutateAsync({
        servidor_id: servidorId,
        causal,
        hechos: hechos.trim(),
        fecha_solicitud: toIso(fechaSolicitud)!,
        numero_tramite_mdt: numeroTramite.trim() || null,
        inspectoria: inspectoria.trim() || null,
      })
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Solicitar visto bueno"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="sm">
        <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />}>
          El visto bueno lo resuelve el Inspector del Trabajo, no la institución.
          Aquí se registra el trámite y, más adelante, la resolución que emita el
          Ministerio. Solo aplica a obreros bajo Código del Trabajo.
        </Alert>

        <BuscarServidorSelect
          label="Trabajador"
          value={servidorId}
          onChange={setServidorId}
          required
        />

        <Select
          label="Causal (Art. 172 del Código del Trabajo)"
          placeholder="Seleccione la causal invocada"
          data={CAUSAL_OPTIONS}
          value={causal}
          onChange={(v) => setCausal(v as CausalVistoBueno | null)}
          searchable
          {...contained}
        />

        <Textarea
          label="Fundamento de hecho"
          placeholder="Relate los hechos que sustentan la solicitud"
          rows={4}
          value={hechos}
          onChange={(e) => setHechos(e.currentTarget.value)}
          {...contained}
        />

        <DatePickerInput
          label="Fecha de presentación de la solicitud"
          value={fechaSolicitud}
          onChange={(v) => setFechaSolicitud(v as Date | null)}
          valueFormat="DD/MM/YYYY"
          {...contained}
        />

        <TextInput
          label="Número de trámite del Ministerio del Trabajo"
          description="Opcional: puede registrarse después, al notificarse el trámite."
          placeholder="Ej: MDT-VB-2026-0042"
          value={numeroTramite}
          onChange={(e) => setNumeroTramite(e.currentTarget.value)}
          {...contained}
        />

        <TextInput
          label="Inspectoría"
          placeholder="Ej: Inspectoría del Trabajo de Esmeraldas"
          value={inspectoria}
          onChange={(e) => setInspectoria(e.currentTarget.value)}
          {...contained}
        />

        {error && <Alert variant="light" color="red">{error}</Alert>}

        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={handleClose}>Cancelar</Button>
          <Button color="emerald" loading={crearVistoBueno.isPending} onClick={submit}>
            Registrar solicitud
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
