'use client'

import { useState } from 'react'
import { Alert, Button, Group, Modal, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useContratoMutations } from '../hooks/useContratoMutations'
import type { ContratoConRelaciones } from '@/types/api'

/**
 * Mueve la fecha de vencimiento de un vínculo vigente.
 *
 * Cubre los dos casos que la mueven en la práctica: una **prórroga** —los
 * contratos de servicios ocasionales y profesionales nacen con plazo y se
 * extienden cuando la necesidad institucional continúa— y la **corrección** de
 * una fecha mal digitada al darlo de alta.
 *
 * Es lo único editable de un contrato ya creado: la modalidad y el puesto
 * exigen cerrar el vínculo y abrir otro bajo una acción de personal. Y el
 * motivo es obligatorio porque los dos casos se ven idénticos en la base —
 * queda en el registro de auditoría junto con la fecha anterior y quién lo
 * hizo.
 */
interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  contrato: ContratoConRelaciones | null
}

const SIN_PLAZO_PROHIBIDO = 'servicios_profesionales'

function toDate(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.split('T')[0].split('-').map(Number)
  return new Date(y, m - 1, d)
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function legible(f?: string | null): string {
  if (!f) return 'sin plazo'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

export function ReprogramarPlazoModal({ opened, onClose, servidorId, contrato }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { reprogramarPlazo } = useContratoMutations(servidorId)

  const [fechaFin, setFechaFin] = useState<Date | null>(null)
  const [motivo, setMotivo] = useState('')
  const [errores, setErrores] = useState<{ fecha?: string; motivo?: string }>({})

  const cerrar = () => {
    setFechaFin(null)
    setMotivo('')
    setErrores({})
    onClose()
  }

  if (!contrato) return null

  const exigePlazo = contrato.tipo_nombramiento === SIN_PLAZO_PROHIBIDO
  const inicio = toDate(contrato.fecha_inicio)
  const nueva = fromDate(fechaFin)

  const guardar = () => {
    const nuevos: typeof errores = {}

    // El backend impide las dos cosas; avisarlo aquí evita un viaje que ya se
    // sabe que va a fallar.
    if (exigePlazo && !nueva) {
      nuevos.fecha = 'Un contrato de Servicios Profesionales no puede quedarse sin vencimiento.'
    }
    if (nueva && inicio && new Date(nueva) < inicio) {
      nuevos.fecha = 'La fecha de fin no puede ser anterior al inicio del contrato.'
    }
    if (motivo.trim().length < 5) {
      nuevos.motivo = 'Explique el cambio: si es una prórroga o una corrección.'
    }

    if (Object.keys(nuevos).length > 0) {
      setErrores(nuevos)
      return
    }

    reprogramarPlazo.mutate(
      { contratoId: Number(contrato.id), fecha_fin: nueva, motivo: motivo.trim() },
      { onSuccess: cerrar },
    )
  }

  return (
    <Modal
      opened={opened}
      onClose={cerrar}
      title="Reprogramar el plazo del contrato"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <Alert color="blue" variant="light" icon={<IconInfoCircle size={16} />}>
          Es el único dato editable de un vínculo ya creado. Cambiar la
          modalidad o el puesto exige cerrarlo y abrir otro bajo su acción de
          personal.
        </Alert>

        <div>
          <Text size="xs" fw={600} c="dimmed" tt="uppercase">Vencimiento actual</Text>
          <Text size="sm" fw={600}>{legible(contrato.fecha_fin)}</Text>
          <Text size="xs" c="dimmed">
            Contrato desde el {legible(contrato.fecha_inicio)}
          </Text>
        </div>

        <DatePickerInput
          label="Nuevo vencimiento"
          placeholder={exigePlazo ? 'Seleccionar fecha' : 'Vacío = sin plazo'}
          valueFormat="YYYY-MM-DD"
          clearable={!exigePlazo}
          minDate={inicio ?? undefined}
          // Este modal se abre dentro del drawer del expediente, y el
          // calendario quedaba por debajo de ambos: hay que levantarlo sobre
          // la pila de capas que ya hay encima.
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          {...contained}
          value={fechaFin}
          onChange={(d) => {
            setFechaFin(typeof d === 'string' ? toDate(d) : d)
            setErrores((e) => ({ ...e, fecha: undefined }))
          }}
          error={errores.fecha}
        />

        {!exigePlazo && !nueva && (
          <Alert color="orange" variant="light" icon={<IconAlertTriangle size={16} />}>
            Sin fecha de vencimiento el vínculo deja de tener término: no se
            generará su Cesación de Funciones por plazo cumplido.
          </Alert>
        )}

        <Textarea
          label="Motivo"
          placeholder="Ej. Prórroga autorizada mediante memorando DTH-2026-0184"
          description="Queda en el registro de auditoría junto con la fecha anterior y quién hizo el cambio."
          minRows={3}
          {...contained}
          value={motivo}
          onChange={(e) => {
            setMotivo(e.currentTarget.value)
            setErrores((err) => ({ ...err, motivo: undefined }))
          }}
          error={errores.motivo}
        />

        <Group justify="flex-end">
          <Button variant="default" onClick={cerrar}>Cancelar</Button>
          <Button
            color="emerald"
            loading={reprogramarPlazo.isPending}
            onClick={guardar}
          >
            Reprogramar
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
