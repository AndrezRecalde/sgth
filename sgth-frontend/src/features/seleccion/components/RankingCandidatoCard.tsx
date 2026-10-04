'use client'

import { Button, Card, Checkbox, Group, Progress, Stack, Text, ThemeIcon } from '@mantine/core'
import { IconMedal, IconMedal2, IconTrophy, IconUserCheck } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { dictamenHabilitaIncorporacion } from '@/features/dispensario/services/solicitudCertificacionOptions'
import { TONO_POSTULANTE } from '../services/convocatoriaService'
import type { Postulante } from '../services/convocatoriaService'

const ESTADO_LABELS: Record<string, string> = {
  inscrito:           'Inscrito',
  en_evaluacion:      'En evaluación',
  aprobado:           'Aprobado',
  reprobado:          'Reprobado',
  descalificado:      'No apto',
  seleccionado:       'Ganador',
  ganador_potencial:  'En evaluación médica',
  no_seleccionado:    'No seleccionado',
  lista_espera:       'Lista de espera',
  incorporado:        'Incorporado',
}

export const nombreCandidato = (p: Postulante) =>
  [p.apellidos, p.segundo_apellido, p.nombres, p.segundo_nombre].filter(Boolean).join(' ')

function PosicionIcon({ pos }: { pos: number }) {
  if (pos === 1) return <ThemeIcon size="md" color="amber.4" variant="filled" radius="xl"><IconTrophy size={14} /></ThemeIcon>
  if (pos === 2) return <ThemeIcon size="md" color="slate.4" variant="filled" radius="xl"><IconMedal size={14} /></ThemeIcon>
  if (pos === 3) return <ThemeIcon size="md" color="amber.8" variant="light" radius="xl"><IconMedal2 size={14} /></ThemeIcon>
  return <ThemeIcon size="md" color="slate.4" variant="light" radius="xl"><Text size="xs" fw={700}>{pos}</Text></ThemeIcon>
}

/**
 * Dónde está el ganador enviado al Dispensario y, si ya tiene dictamen de
 * aptitud, el botón para incorporarlo (2026-10-04). Es lo que cierra el
 * concurso formal: con el último ganador incorporado, la convocatoria se
 * finaliza sola. Antes había un «Declarar ganador oficial» que no miraba el
 * dictamen ni creaba el expediente.
 *
 * Un no apto ya no llega aquí: el dictamen lo pasa a «descalificado».
 */
function EstadoIncorporacion({ p, puedeIncorporar, incorporando, onIncorporar }: {
  p: Postulante
  puedeIncorporar: boolean
  incorporando: boolean
  onIncorporar: (p: Postulante) => void
}) {
  const s = p.solicitud_certificacion
  const apto = s?.estado === 'completada' && dictamenHabilitaIncorporacion(s.dictamen)

  return (
    <Group justify="space-between" gap="xs">
      <Text size="xs" c="dimmed">
        {apto
          ? 'Con dictamen de aptitud: listo para incorporar.'
          : 'Esperando el dictamen del Dispensario Médico.'}
      </Text>
      {apto && (
        <Button
          size="xs"
          leftSection={<IconUserCheck size={14} />}
          disabled={!puedeIncorporar}
          loading={incorporando}
          onClick={() => onIncorporar(p)}
        >
          Confirmar incorporación
        </Button>
      )}
    </Group>
  )
}

interface Props {
  p: Postulante
  posicion: number
  /** Se puede marcar para enviarlo al Dispensario. */
  seleccionable: boolean
  seleccionado: boolean
  sinCupo: boolean
  onAlternar: (id: number) => void
  puedeIncorporar: boolean
  incorporando: boolean
  onIncorporar: (p: Postulante) => void
}

export function RankingCandidatoCard({
  p, posicion, seleccionable, seleccionado, sinCupo, onAlternar,
  puedeIncorporar, incorporando, onIncorporar,
}: Props) {
  const total = Number(p.evaluacion?.puntaje_total ?? 0)
  const aprueba = total >= 70
  const destacado = posicion === 1 && aprueba

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{
        borderColor: destacado ? 'var(--mantine-color-amber-4)' : undefined,
        borderWidth: destacado ? 2 : 1,
      }}
    >
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <PosicionIcon pos={posicion} />
            <Stack gap={0}>
              <Text size="sm" fw={600}>{nombreCandidato(p)}</Text>
              <Text size="xs" c="dimmed">{p.cedula}</Text>
            </Stack>
          </Group>
          <Group gap="xs" wrap="nowrap">
            <StatusBadge tone={TONO_POSTULANTE[p.estado] ?? 'neutral'}>
              {ESTADO_LABELS[p.estado] ?? p.estado}
            </StatusBadge>
            <StatusBadge tone={aprueba ? 'success' : 'danger'} size="lg">
              {total.toFixed(2)} pts
            </StatusBadge>
          </Group>
        </Group>

        <Group gap="xs">
          <Text size="xs" c="dimmed">Méritos: {Number(p.evaluacion?.puntaje_meritos ?? 0).toFixed(2)}</Text>
          <Text size="xs" c="dimmed">·</Text>
          <Text size="xs" c="dimmed">Oposición: {Number(p.evaluacion?.puntaje_oposicion ?? 0).toFixed(2)}</Text>
        </Group>

        <Progress value={total} color={aprueba ? undefined : 'red'} size="xs" radius="xl" />

        {p.estado === 'ganador_potencial' && (
          <EstadoIncorporacion
            p={p}
            puedeIncorporar={puedeIncorporar}
            incorporando={incorporando}
            onIncorporar={onIncorporar}
          />
        )}

        {p.estado === 'descalificado' && (
          <Text size="xs" c="dimmed">
            El Dispensario Médico lo declaró no apto: no puede ser incorporado.
          </Text>
        )}

        {aprueba && seleccionable && (
          <Group justify="flex-end">
            <Checkbox
              label="Enviar al Dispensario"
              checked={seleccionado}
              onChange={() => onAlternar(p.id)}
              // Sin cupo restante solo se puede desmarcar.
              disabled={!seleccionado && sinCupo}
              size="sm"
            />
          </Group>
        )}
      </Stack>
    </Card>
  )
}
