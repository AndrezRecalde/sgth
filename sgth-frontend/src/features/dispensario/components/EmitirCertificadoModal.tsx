'use client'

import {
  Modal, Stack, Textarea,
  Button, Group, Text, Alert,
  Badge, 
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useState } from 'react'
import { IconCheck, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useEmitirCertificado } from '../hooks/useCertificado'
import { BuscarCie10Input } from './BuscarCie10Input'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../services/cie10Service'

interface Props {
  opened:     boolean
  onClose:    () => void
  consulta:   ConsultaMedica
  esFamiliar: boolean
}

const DIAS_MAX = 3

function fromDate(d: Date | null): string {
  if (!d) return ''
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function calcularDias(
  inicio: Date | null,
  fin: Date | null
): number {
  if (!inicio || !fin) return 0
  const diff = fin.getTime() - inicio.getTime()
  return Math.floor(diff / (1000 * 60 * 60 * 24)) + 1
}

export function EmitirCertificadoModal({
  opened, onClose, consulta, esFamiliar,
}: Props) {
  const contained = useContainedInput()
  const emitir    = useEmitirCertificado(consulta.id)

  const [rango, setRango] = useState<[Date | null, Date | null]>(
    [null, null]
  )
  const [cie10Sel, setCie10Sel] =
    useState<DiagnosticoCie10 | null>(null)
  const [observaciones, setObservaciones] = useState('')
  const [errorRango, setErrorRango] = useState<string | null>(null)

  const [fechaInicio, fechaFin] = rango
  const dias = calcularDias(fechaInicio, fechaFin)
  const rangoExcede = dias > DIAS_MAX

  const toDateObj = (v: Date | string | null): Date | null => {
    if (!v) return null
    if (v instanceof Date) return v
    const [y, m, d] = String(v).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d)
  }

  // Mantine entrega el rango como [inicio, fin]; cada extremo puede ser un
  // Date, la cadena ISO del DatePickerInput, o null si aún no se ha elegido.
  const handleRangoChange = (
    value: [Date | string | null, Date | string | null]
  ) => {
    const inicio = toDateObj(value[0])
    const fin    = toDateObj(value[1])
    setRango([inicio, fin])
    const d = calcularDias(inicio, fin)
    if (d > DIAS_MAX) {
      setErrorRango(
        `El rango seleccionado es de ${d} días. Máximo permitido: ${DIAS_MAX}.`
      )
    } else {
      setErrorRango(null)
    }
  }

  const handleSubmit = () => {
    if (!fechaInicio || !fechaFin) {
      setErrorRango('Selecciona el rango de fechas del reposo.')
      return
    }
    if (rangoExcede) return

    emitir.mutate(
      {
        consulta_medica_id:   consulta.id,
        dias_reposo:          dias,
        fecha_inicio:         fromDate(fechaInicio),
        fecha_fin:            fromDate(fechaFin),
        diagnostico_cie10_id: cie10Sel?.id ?? null,
        observaciones:        observaciones || null,
      },
      {
        onSuccess: () => {
          setRango([null, null])
          setCie10Sel(null)
          setObservaciones('')
          setErrorRango(null)
          onClose()
        },
      }
    )
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Emitir certificado médico"
      size="md"
      radius="xl"
    >
      <Stack gap="sm">
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="blue"
          variant="light"
        >
          <Text size="xs">
            Máximo <Text span fw={600}>{DIAS_MAX} días de reposo</Text>
            {' '}por certificado (normativa MSP Ecuador).
            {!esFamiliar && (
              <> El permiso de asistencia se generará
              automáticamente.</>
            )}
          </Text>
        </Alert>

        <Stack gap={4}>
          <DatePickerInput
            type="range"
            label="Rango de reposo"
            placeholder="Selecciona fecha inicio y fin"
            valueFormat="DD/MM/YYYY"
            maxDate={
              fechaInicio instanceof Date
                ? new Date(
                    fechaInicio.getFullYear(),
                    fechaInicio.getMonth(),
                    fechaInicio.getDate() + DIAS_MAX - 1
                  )
                : undefined
            }
            {...contained}
            value={rango}
            onChange={handleRangoChange}
            error={errorRango}
          />

          {dias > 0 && !rangoExcede && (
            <Group gap="xs">
              <Text size="xs" c="dimmed">Días de reposo:</Text>
              <Badge size="sm" variant="light" color="emerald">
                {dias} día{dias !== 1 ? 's' : ''}
              </Badge>
            </Group>
          )}
        </Stack>

        <BuscarCie10Input
          value={cie10Sel}
          onChange={setCie10Sel}
        />

        <Textarea
          label="Observaciones (opcional)"
          placeholder="Indicaciones adicionales del reposo"
          autosize
          minRows={2}
          {...contained}
          value={observaciones}
          onChange={(e) => setObservaciones(e.currentTarget.value)}
        />

        <Group justify="flex-end" mt="sm">
          <Button
            variant="default"
            onClick={() => {
              setRango([null, null])
              setCie10Sel(null)
              setObservaciones('')
              setErrorRango(null)
              onClose()
            }}
          >
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={emitir.isPending}
            disabled={!fechaInicio || !fechaFin || rangoExcede}
            onClick={handleSubmit}
          >
            Emitir certificado
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
