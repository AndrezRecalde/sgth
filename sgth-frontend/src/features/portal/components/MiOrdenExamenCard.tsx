'use client'

import { Button, Group, Paper, Stack, Text } from '@mantine/core'
import { IconCircleCheck, IconCircleDashed, IconCircleX, IconClockHour4, IconPrinter, IconUpload } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFechaMes } from '@/lib/fecha'
import { enEspera, ESTADO_MI_ORDEN, porEnviar } from '../constants/misExamenes'
import { useMisExamenesMutations } from '../hooks/useMisExamenes'
import { MisEnviosDeOrden } from './MisEnviosDeOrden'
import type { MiExamenDeOrden, MiOrdenExamen } from '../services/misExamenesService'

interface Props {
  orden:    MiOrdenExamen
  onEnviar: (orden: MiOrdenExamen) => void
}

/**
 * Una orden de exámenes del servidor: qué se le pidió, dónde y cómo va cada
 * examen; el impreso, por si lo perdió, y enviar lo que le dio el laboratorio.
 */
export function MiOrdenExamenCard({ orden, onEnviar }: Props) {
  const { verOrden } = useMisExamenesMutations()
  const estado = ESTADO_MI_ORDEN[orden.estado]
  const esperando = enEspera(orden)
  const puedeEnviar = porEnviar(orden).length > 0

  return (
    <Paper withBorder radius="md" p="md">
      <Stack gap="sm">
        <Group justify="space-between" align="flex-start" wrap="wrap" gap="xs">
          <Stack gap={2}>
            <Group gap="xs">
              <Text fw={600}>Orden {orden.folio}</Text>
              <StatusBadge size="sm" tone={estado.tone}>{estado.label}</StatusBadge>
              {orden.vencida && <StatusBadge size="sm" tone="warning" variant="outline">Vencida</StatusBadge>}
              {orden.prioridad === 'urgente' && <StatusBadge size="sm" tone="danger" variant="dot">Urgente</StatusBadge>}
              {orden.ocupacional && <StatusBadge size="sm" tone="neutral">Evaluación ocupacional</StatusBadge>}
            </Group>
            <Text size="xs" c="dimmed">
              Del {formatFechaMes(orden.fecha_solicitud)} · pedida por {orden.solicitante}
            </Text>
          </Stack>
          <Group gap="xs">
            {orden.estado !== 'anulada' && (
              <Button size="xs" variant="default" leftSection={<IconPrinter size={14} />}
                loading={verOrden.isPending} onClick={() => verOrden.mutate(orden)}>
                Descargar orden
              </Button>
            )}
            {puedeEnviar && (
              <Button size="xs" leftSection={<IconUpload size={14} />} onClick={() => onEnviar(orden)}>
                Enviar resultado
              </Button>
            )}
          </Group>
        </Group>

        {orden.estado === 'anulada' && (
          <Text size="xs" c="dimmed">El Dispensario anuló esta orden: no la lleve al laboratorio.</Text>
        )}
        {orden.vencida && (
          <Text size="xs" c="dimmed">
            Pasaron más de 90 días desde que se pidió. Si todavía no se hizo los exámenes, pida una orden nueva en el Dispensario.
          </Text>
        )}
        {(orden.laboratorio_destino || orden.indicaciones) && (
          <Stack gap={2}>
            {orden.laboratorio_destino && <Text size="sm">Laboratorio: {orden.laboratorio_destino}</Text>}
            {orden.indicaciones && <Text size="sm">Indicaciones: {orden.indicaciones}</Text>}
          </Stack>
        )}

        <Stack gap={4}>
          {(orden.items ?? []).map((i) => (
            <ExamenDeLaOrden key={i.id} examen={i} esperando={esperando.includes(i.id)}
              anulada={orden.estado === 'anulada'} />
          ))}
        </Stack>

        <MisEnviosDeOrden orden={orden} />
      </Stack>
    </Paper>
  )
}

interface PropsExamen {
  examen:    MiExamenDeOrden
  /** Hay un envío suyo que lo cubre y espera validación. */
  esperando: boolean
  /** De una orden anulada: ya no se espera, aunque siga «pendiente». */
  anulada:   boolean
}

/** Un examen y cómo va, dicho para el paciente. */
function ExamenDeLaOrden({ examen, esperando, anulada }: PropsExamen) {
  const { Icono, color, texto } = anulada && examen.estado === 'pendiente'
    ? { Icono: IconCircleX, color: 'dimmed', texto: 'Sin efecto' }
    : esperando && examen.estado !== 'recibido'
    ? { Icono: IconClockHour4, color: SEMANTIC_COLOR.warning, texto: 'Usted envió el resultado; falta que lo valide un médico' }
    : {
      pendiente:    { Icono: IconCircleDashed, color: 'dimmed',               texto: 'Pendiente' },
      recibido:     { Icono: IconCircleCheck,  color: SEMANTIC_COLOR.success, texto: 'El Dispensario tiene el resultado' },
      no_realizado: { Icono: IconCircleX,      color: 'dimmed',               texto: `No realizado${examen.motivo_no_realizado ? `: ${examen.motivo_no_realizado}` : ''}` },
    }[examen.estado]

  return (
    <Group gap="xs" wrap="nowrap" align="flex-start">
      <Text span c={color} lh={1} mt={2} aria-hidden><Icono size={16} /></Text>
      <Stack gap={0}>
        <Text size="sm">{examen.nombre}{examen.nota ? ` (${examen.nota})` : ''}</Text>
        <Text size="xs" c="dimmed">{texto}</Text>
      </Stack>
    </Group>
  )
}
