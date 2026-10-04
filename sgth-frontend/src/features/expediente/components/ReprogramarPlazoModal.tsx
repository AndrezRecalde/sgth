'use client'

import { useState } from 'react'
import { Alert, Stack, Text, Textarea } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useContratoMutations } from '../hooks/useContratoMutations'
import type { ContratoConRelaciones } from '@/types/api'
import { formatFecha, toDateValue, fromDateValueOrNull } from '@/lib/fecha'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'

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
  /** Si el vínculo cubre una ausencia: hasta cuándo dura esa ausencia. */
  hastaReemplazo?: string | null
}

/**
 * Las modalidades que no pueden quedarse sin término. Espejo de
 * `TipoNombramiento::exigePlazo()`: hasta el 2026-10-03 aquí solo estaba
 * Servicios Profesionales, y un ocasional quedaba indefinido.
 */
const EXIGEN_PLAZO = ['servicios_ocasionales', 'servicios_profesionales']

/** Como formatFecha, pero un contrato sin fecha de fin no tiene plazo. */
const legible = (f?: string | null): string => (f ? formatFecha(f) : 'sin plazo')

export function ReprogramarPlazoModal({
  opened, onClose, servidorId, contrato, hastaReemplazo,
}: Props) {
  const contained = useContainedInput()
  const { reprogramarPlazo } = useContratoMutations(servidorId)

  // Arranca en el vencimiento actual: vacío significa «sin plazo», y quien solo
  // escribía el motivo dejaba el vínculo indefinido sin haberlo elegido.
  const [fechaFin, setFechaFin] = useState<Date | null>(toDateValue(contrato?.fecha_fin))
  const [motivo, setMotivo] = useState('')
  const [errores, setErrores] = useState<{ fecha?: string; motivo?: string }>({})

  const cerrar = () => {
    setMotivo('')
    setErrores({})
    onClose()
  }

  if (!contrato) return null

  const tipo = contrato.tipo_nombramiento ?? ''
  const exigePlazo = EXIGEN_PLAZO.includes(tipo) || !!hastaReemplazo
  const inicio = contrato.fecha_inicio?.slice(0, 10) ?? null
  const nueva = fromDateValueOrNull(fechaFin)

  const guardar = () => {
    const nuevos: typeof errores = {}

    // El backend impide todo esto; avisarlo aquí evita un viaje que ya se sabe
    // que va a fallar. Se comparan las fechas como texto «AAAA-MM-DD»: con
    // `new Date('2026-01-01')` —medianoche UTC, las 19:00 del día anterior en
    // Guayaquil— elegir el mismo día del inicio salía como «anterior».
    if (exigePlazo && !nueva) {
      nuevos.fecha = hastaReemplazo
        ? 'Un reemplazo no puede quedarse sin vencimiento.'
        : `Un vínculo de ${etiquetaNombramiento(tipo)} no puede quedarse sin vencimiento.`
    }
    if (nueva && inicio && nueva < inicio) {
      nuevos.fecha = 'La fecha de fin no puede ser anterior al inicio del contrato.'
    }
    if (nueva && hastaReemplazo && nueva > hastaReemplazo) {
      nuevos.fecha = `El reemplazo no puede ir más allá del ${formatFecha(hastaReemplazo)}, cuando termina la ausencia que cubre.`
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
    <SgthModal
      opened={opened}
      onClose={cerrar}
      title="Reprogramar el plazo del contrato"
      size="md"
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Es el único dato editable de un vínculo ya creado. Cambiar la
          modalidad o el puesto exige cerrarlo y abrir otro bajo su acción de
          personal.
        </Alert>

        <div>
          <Text size="xs" fw={600} c="dimmed" tt="uppercase">Vencimiento actual</Text>
          <Text size="sm" fw={600}>{legible(contrato.fecha_fin)}</Text>
          <Text size="xs" c="dimmed">
            Contrato desde el {legible(contrato.fecha_inicio)}
            {hastaReemplazo && ` · cubre una ausencia hasta el ${formatFecha(hastaReemplazo)}`}
          </Text>
        </div>

        <DatePickerInput
          label="Nuevo vencimiento"
          placeholder={exigePlazo ? 'Seleccionar fecha' : 'Vacío = sin plazo'}
          valueFormat="DD/MM/YYYY"
          clearable={!exigePlazo}
          minDate={toDateValue(inicio) ?? undefined}
          maxDate={toDateValue(hastaReemplazo) ?? undefined}
          // El calendario se abre sobre un modal: hay que levantarlo por
          // encima de la pila de capas que ya hay.
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          {...contained}
          value={fechaFin}
          onChange={(d) => {
            setFechaFin(toDateValue(typeof d === 'string' ? d : fromDateValueOrNull(d)))
            setErrores((e) => ({ ...e, fecha: undefined }))
          }}
          error={errores.fecha}
        />

        {!exigePlazo && !nueva && (
          <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}>
            Sin fecha de vencimiento el vínculo deja de tener término: queda
            vigente hasta que una acción de personal lo cierre.
          </Alert>
        )}

        <Textarea
          label="Motivo"
          placeholder="Ej. Prórroga autorizada mediante memorando DTH-2026-0184"
          description="Queda en el registro de auditoría junto con la fecha anterior y quién hizo el cambio."
          autosize
          minRows={3}
          {...contained}
          value={motivo}
          onChange={(e) => {
            setMotivo(e.currentTarget.value)
            setErrores((err) => ({ ...err, motivo: undefined }))
          }}
          error={errores.motivo}
        />
      </Stack>
      <ModalFooter
        onCancel={cerrar}
        submitLabel="Reprogramar"
        submitting={reprogramarPlazo.isPending}
        onSubmit={guardar}
      />
    </SgthModal>
  )
}
