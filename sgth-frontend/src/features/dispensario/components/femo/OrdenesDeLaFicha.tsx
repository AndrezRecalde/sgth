'use client'

import { Button, Card, Group, Stack, Text, Tooltip } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPrinter, IconTestPipe } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { useOrdenesDeFicha, useOrdenExamenPdf } from '../../hooks/useExamenes'
import { useExamenesPorRiesgo } from '../../hooks/useExamenesPorRiesgo'
import { claveRiesgo } from '../../services/examenesPorRiesgoService'
import { ESTADO_ORDEN } from '../../utils/resultadoDeOrden'
import { ItemsDeOrden } from '../examenes/ItemsDeOrden'
import { SolicitarExamenesModal } from '../examenes/SolicitarExamenesModal'
import type { FactorRiesgoForm } from '../../schemas/femoEvaluacion.schema'

interface Props {
  /** La ficha guardada; sin borrador todavía no hay a qué colgar la orden. */
  fichaId:  number | null
  /** Los riesgos marcados en la sección G: de ahí salen los exámenes propuestos. */
  factores: FactorRiesgoForm[]
}

/**
 * Pedir los exámenes de la evaluación desde la ficha (5.6 del diseño de
 * exámenes): propone los que corresponden a los riesgos del puesto (D6) y
 * enseña las órdenes ya pedidas, para imprimirlas.
 */
export function OrdenesDeLaFicha({ fichaId, factores }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: ordenes = [] } = useOrdenesDeFicha(fichaId)
  const { data: mapa = [] } = useExamenesPorRiesgo()
  const { abrir: abrirPdf, abriendo } = useOrdenExamenPdf()

  const marcados = new Set(factores.filter((f) => f.presente !== false).map((f) => claveRiesgo(f.categoria, f.factor)))
  const propuestos = [...new Set(mapa
    .filter((r) => marcados.has(claveRiesgo(r.categoria, r.factor)))
    .flatMap((r) => r.examenes.map((e) => e.id)))]

  return (
    <Stack gap="xs">
      <Group justify="space-between">
        <Text size="xs" c="dimmed">
          {propuestos.length
            ? `Los riesgos marcados en la sección G proponen ${propuestos.length} ${propuestos.length === 1 ? 'examen' : 'exámenes'}.`
            : 'Los riesgos marcados en la sección G no proponen exámenes.'}
        </Text>
        <Tooltip label="Guarde el borrador para pedir exámenes" disabled={!!fichaId} withArrow>
          <Button size="xs" variant="light" leftSection={<IconTestPipe size={14} />} disabled={!fichaId} onClick={open}>
            Pedir exámenes
          </Button>
        </Tooltip>
      </Group>

      {ordenes.map((o) => (
        <Card key={o.id} withBorder radius="md" p="xs">
          <Group justify="space-between" wrap="nowrap" align="flex-start">
            <Stack gap={4}>
              <Group gap="xs">
                <Text size="sm" fw={600} ff="monospace">{o.folio}</Text>
                <StatusBadge size="xs" tone={ESTADO_ORDEN[o.estado].tone}>{ESTADO_ORDEN[o.estado].label}</StatusBadge>
                <Text size="xs" c="dimmed">{formatFechaMes(o.fecha_solicitud)}</Text>
              </Group>
              <ItemsDeOrden items={o.items} />
            </Stack>
            <Button size="compact-xs" variant="light" leftSection={<IconPrinter size={13} />}
              loading={abriendo === o.id} onClick={() => abrirPdf(o.id, o.folio)}>
              Imprimir
            </Button>
          </Group>
        </Card>
      ))}

      {fichaId && (
        <SolicitarExamenesModal opened={opened} onClose={close}
          origen={{ tipo: 'ficha', fichaId, propuestos }} />
      )}
    </Stack>
  )
}
