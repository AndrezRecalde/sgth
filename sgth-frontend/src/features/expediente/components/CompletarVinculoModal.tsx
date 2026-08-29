'use client'

import { useState } from 'react'
import {
  Alert, Button, Grid, Group, Modal, NumberInput, Stack, Switch, Text, TextInput,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { admiteMarcacion, esLosep, remuneracionEsHeredada } from '../utils/nombramiento'
import type { MovimientoPersonal } from '@/types/api'

/** Nombramientos cuyo vínculo lleva plazo pactado. */
const CON_PLAZO = ['servicios_ocasionales', 'servicios_profesionales']

/**
 * Cierre de un ingreso: los datos que el contrato necesita para nacer, en el
 * propio acto de aprobar la acción.
 *
 * Existe únicamente para ese momento. Una acción suscrita ya no se edita —el
 * documento circuló—, pero el vínculo todavía tiene que materializarse con
 * número y remuneración, y este es el único formulario que puede aportarlos
 * sin reabrir el acto. Mientras la acción sigue en borrador no se usa: ahí
 * todo se corrige en el formulario completo de la acción.
 */
interface Props {
  opened: boolean
  onClose: () => void
  movimiento: MovimientoPersonal | null
  /** Se dispara solo cuando el guardado tuvo éxito, no al cancelar. */
  onSaved?: () => void
}

function fechaLegible(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

export function CompletarVinculoModal({ opened, onClose, movimiento, onSaved }: Props) {
  const { isMobile } = useMobileBreakpoint()

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Aprobar y registrar el vínculo"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      {opened && movimiento && (
        <Formulario
          key={movimiento.id}
          movimiento={movimiento}
          onClose={onClose}
          onSaved={onSaved}
        />
      )}
    </Modal>
  )
}

function Formulario({
  movimiento,
  onClose,
  onSaved,
}: {
  movimiento: MovimientoPersonal
  onClose: () => void
  onSaved?: () => void
}) {
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations(movimiento.servidor_id)

  const nombramiento = movimiento.tipo_nombramiento_propuesto ?? null
  const derivaDelPuesto = esLosep(nombramiento)
  const llevaPlazo = nombramiento ? CON_PLAZO.includes(nombramiento) : false

  const puesto = (movimiento as unknown as {
    puesto_destino?: {
      rmu?: string | number | null
      partida_presupuestaria?: { id: number } | null
    } | null
  }).puesto_destino

  // La RMU solo se sugiere en LOSEP, donde sale del grupo ocupacional del
  // puesto. En Código del Trabajo y Servicios Profesionales se negocia en el
  // contrato, así que el campo arranca vacío a propósito.
  const rmuSugerida = derivaDelPuesto && puesto?.rmu != null ? Number(puesto.rmu) : ''

  const rmuHeredada = remuneracionEsHeredada(
    nombramiento,
    puesto?.rmu != null ? Number(puesto.rmu) : null,
  )

  const [numeroContrato, setNumeroContrato] = useState(movimiento.numero_contrato ?? '')
  const [remuneracion, setRemuneracion] = useState<number | ''>(
    movimiento.remuneracion_propuesta != null
      ? Number(movimiento.remuneracion_propuesta)
      : rmuSugerida,
  )
  const [resolucion, setResolucion] = useState(movimiento.resolucion_numero ?? '')
  const [partidaId, setPartidaId] = useState<number | null>(
    movimiento.partida_presupuestaria_id
      ?? puesto?.partida_presupuestaria?.id
      ?? null,
  )
  const [puedeMarcar, setPuedeMarcar] = useState<boolean>(movimiento.puede_marcar ?? false)
  const [fechaFin, setFechaFin] = useState<Date | null>(
    movimiento.fecha_fin_propuesta ? new Date(movimiento.fecha_fin_propuesta) : null,
  )
  const [error, setError] = useState<string | null>(null)

  const toIso = (d: Date | null) =>
    d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : null

  const datos = () => ({
    numero_contrato: numeroContrato.trim() || null,
    remuneracion_propuesta: remuneracion === '' ? null : Number(remuneracion),
    resolucion_numero: resolucion.trim() || null,
    partida_presupuestaria_id: partidaId,
    puede_marcar: puedeMarcar,
    fecha_fin_propuesta: llevaPlazo ? toIso(fechaFin) : null,
  })

  const submit = () => {
    // Obligatorios: el contrato nace en este acto y no admite quedar a medias.
    const faltantes: string[] = []
    if (!numeroContrato.trim()) faltantes.push('número de contrato')
    if (remuneracion === '') faltantes.push('remuneración')

    if (faltantes.length > 0) {
      return setError(`Falta ${faltantes.join(' y ')} para registrar el vínculo.`)
    }

    setError(null)

    transicionar
      .mutateAsync({ id: Number(movimiento.id), estado: 'registrada', ...datos() })
      .then(() => { onClose(); onSaved?.() })
      .catch(() => {})
  }

  const pendiente = transicionar.isPending

  return (
    <Stack gap="sm">
      <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16} />}>
        Al registrar se crea el vínculo laboral con estos datos. Después la acción
        queda inmutable: solo se corrige registrando una acción nueva.
      </Alert>

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Número de contrato"
            placeholder="Ej: CT-2026-0099"
            value={numeroContrato}
            onChange={(e) => setNumeroContrato(e.currentTarget.value)}
            required
            {...contained}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Número de resolución"
            placeholder="Opcional"
            value={resolucion}
            onChange={(e) => setResolucion(e.currentTarget.value)}
            {...contained}
          />
        </Grid.Col>
      </Grid>

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Fecha de inicio"
            description="Es la fecha en que rige la acción; para cambiarla se anula y se registra otra."
            value={fechaLegible(movimiento.fecha_efectiva)}
            readOnly
            {...contained}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {llevaPlazo ? (
            <DatePickerInput
              label="Fecha de término"
              description="Servicios Profesionales toma el 31 de diciembre de su año si se deja vacío."
              value={fechaFin}
              onChange={(v) => setFechaFin(v as Date | null)}
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
            />
          ) : (
            <TextInput
              label="Fecha de término"
              description="Este nombramiento no lleva plazo."
              value="Sin plazo"
              readOnly
              {...contained}
            />
          )}
        </Grid.Col>
      </Grid>

      <NumberInput
        label="Remuneración mensual unificada (R.M.U.)"
        description={rmuHeredada
          ? 'Fijada por el grupo ocupacional del puesto. No se edita en régimen LOSEP.'
          : derivaDelPuesto
            ? 'Este puesto no tiene grupo ocupacional asignado: ingrese el monto a mano.'
            : 'Se pacta en el contrato: este régimen no toma la remuneración del puesto.'}
        placeholder="0.00"
        min={0}
        decimalScale={2}
        readOnly={rmuHeredada}
        value={remuneracion}
        onChange={(v) => {
          const n = typeof v === 'number' ? v : parseFloat(String(v))
          setRemuneracion(Number.isFinite(n) ? n : '')
        }}
        required
        {...contained}
      />

      {!derivaDelPuesto && (
        <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Bajo Código del Trabajo y Servicios Profesionales la remuneración es
            la negociada con el trabajador, no la del puesto.
          </Text>
        </Alert>
      )}

      <SelectPartidaPresupuestaria
        value={partidaId}
        onChange={setPartidaId}
        modalidad={nombramiento}
      />

      {/* Servicios profesionales, libre nombramiento y elección popular no
          marcan nunca; el backend fuerza el valor igualmente. */}
      <Switch
        label="Marcación biométrica"
        description={admiteMarcacion(nombramiento)
          ? 'Sugerida según el nombramiento; ajústela si este caso es distinto.'
          : 'Esta modalidad no marca biométrico.'}
        checked={admiteMarcacion(nombramiento) && puedeMarcar}
        disabled={!admiteMarcacion(nombramiento)}
        onChange={(e) => setPuedeMarcar(e.currentTarget.checked)}
      />

      {error && <Alert variant="light" color="red">{error}</Alert>}

      <Group justify="flex-end" mt="md">
        <Button variant="default" onClick={onClose}>Cancelar</Button>
        <Button color="emerald" loading={pendiente} onClick={submit}>
          Registrar vínculo
        </Button>
      </Group>
    </Stack>
  )
}
