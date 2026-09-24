'use client'

import { DataState, StatusBadge } from '@/components/ui'
import {
  Stack, Text, Button, Group,
  Card, ThemeIcon,
} from '@mantine/core'
import { IconPill, IconPlus, IconPrinter } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useQuery } from '@tanstack/react-query'
import { recetaService } from '../services/recetaService'
import { RecetaModal } from './RecetaModal'
import { ItemsRecetaTable } from './ItemsRecetaTable'
import { useEmitirReceta, useRecetaPdf } from '../hooks/useReceta'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { SemanticTone } from '@/config/design.tokens'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

const ESTADO_RECETA: Record<string, { label: string; tone: SemanticTone }> = {
  pendiente:           { label: 'Pendiente',  tone: 'neutral' },
  despachada_parcial:  { label: 'Parcial',    tone: 'warning' },
  despachada_completa: { label: 'Despachada', tone: 'success' },
  anulada:             { label: 'Anulada',    tone: 'danger'  },
  // No es un problema ni un logro: la farmacia no tiene nada que hacer con
  // ella, y con eso queda cerrada.
  externa:             { label: 'Externa',    tone: 'neutral' },
}

export function TabReceta({ turno, consulta }: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const emitir = useEmitirReceta(consulta.id)
  const { abrir: abrirPdf, abriendo } = useRecetaPdf()

  const { data: recetas = [], isLoading, error, refetch } = useQuery({
    queryKey: ['recetas', 'consulta', consulta.id],
    queryFn:  () => recetaService.listarPorConsulta(consulta.id),
    staleTime: 1000 * 30,
  })

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Recetas de esta consulta
          {recetas.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({recetas.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
          loading={emitir.isPending}
        >
          Nueva receta
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las recetas"
        errorHint="No quiere decir que esta consulta no tenga recetas emitidas: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!recetas.length}
        emptyProps={{
          icon: IconPill,
          title: 'Sin recetas',
          description: 'No se han emitido recetas para esta consulta.',
        }}
        skeletonRows={2}
      >
        <Stack gap="sm">
          {recetas.map((receta) => {
            const estadoConfig = ESTADO_RECETA[receta.estado]
              ?? { label: receta.estado, tone: 'neutral' as SemanticTone }
            return (
              <Card key={receta.id} withBorder radius="md" p="sm">
                <Stack gap="xs">
                  <Group justify="space-between">
                    <Group gap="xs">
                      <ThemeIcon
                        size="sm" variant="light"
                      >
                        <IconPill size={12} />
                      </ThemeIcon>
                      <Text size="sm" fw={500}>
                        {formatFechaMes(receta.fecha_emision)}
                      </Text>
                    </Group>
                    <Group gap="xs">
                      {receta.folio && (
                        <Text size="xs" c="dimmed" ff="monospace">
                          {receta.folio}
                        </Text>
                      )}
                      <StatusBadge tone={estadoConfig.tone}>
                        {estadoConfig.label}
                      </StatusBadge>
                      <Button
                        size="compact-xs"
                        variant="light"
                        leftSection={<IconPrinter size={13} />}
                        loading={abriendo === receta.id}
                        onClick={() => abrirPdf(receta.id)}
                      >
                        Imprimir
                      </Button>
                    </Group>
                  </Group>

                  {receta.indicaciones_generales && (
                    <Text size="xs" c="dimmed">
                      {receta.indicaciones_generales}
                    </Text>
                  )}

                  <ItemsRecetaTable
                    receta={receta}
                    consulta={consulta}
                  />
                </Stack>
              </Card>
            )
          })}
        </Stack>
      </DataState>

      <RecetaModal
        opened={modalOpened}
        onClose={cerrarModal}
        turno={turno}
        consulta={consulta}
        onEmitida={cerrarModal}
      />
    </Stack>
  )
}
