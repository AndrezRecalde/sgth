'use client'

import { Stack, Text, Group, Skeleton, Alert, SimpleGrid, Paper } from '@mantine/core'
import { CountBadge, SgthModal, StatusBadge } from '@/components/ui'
import { IconAlertCircle } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { useResultadosAssist } from '../hooks/useAssist'
import { NIVEL_RIESGO_ASSIST_LABELS, TONO_RIESGO_ASSIST } from '../schemas/assist.schema'
import type { ResultadoSustanciaAgregado } from '../services/assistService'
import type { DataTableColumn } from 'mantine-datatable'

type FilaSustancia = ResultadoSustanciaAgregado & { key: string }

interface Props {
  opened: boolean
  onClose: () => void
  campaniaId: number | null
}

export function ResultadosAssistModal({ opened, onClose, campaniaId }: Props) {
  const { data: resultados, isLoading } = useResultadosAssist(campaniaId)

  const columns: DataTableColumn<FilaSustancia>[] = [
    { accessor: 'etiqueta', title: 'Sustancia' },
    { accessor: 'total_consumieron', title: 'Consumieron', textAlign: 'center', width: 100 },
    {
      accessor: 'bajo',
      title: 'Bajo',
      textAlign: 'center',
      width: 80,
      render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.bajo}>{f.bajo}</CountBadge>,
    },
    {
      accessor: 'moderado',
      title: 'Moderado',
      textAlign: 'center',
      width: 90,
      render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.moderado}>{f.moderado}</CountBadge>,
    },
    {
      accessor: 'alto',
      title: 'Alto',
      textAlign: 'center',
      width: 80,
      render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.alto}>{f.alto}</CountBadge>,
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Resultados del tamizaje ASSIST"
      size="xl"
    >
      {isLoading && <Skeleton height={300} radius="md" />}

      {!isLoading && resultados && resultados.total_respuestas === 0 && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
          Todavía no se han registrado respuestas para esta campaña.
        </Alert>
      )}

      {!isLoading && resultados && resultados.total_respuestas > 0 && (
        <Stack gap="md">
          <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }}>
            <Paper withBorder p="sm" radius="md">
              <Text size="xs" c="dimmed">Total de respuestas</Text>
              <Text size="xl" fw={700}>{resultados.total_respuestas}</Text>
            </Paper>
            <Paper withBorder p="sm" radius="md">
              <Text size="xs" c="dimmed">No reportan consumo</Text>
              <Text size="xl" fw={700} c="emerald">{resultados.sin_consumo_reportado}</Text>
            </Paper>
            <Paper withBorder p="sm" radius="md">
              <Text size="xs" c="dimmed">Riesgo alto en alguna sustancia</Text>
              <Text size="xl" fw={700} c="red">{resultados.riesgo_alto_alguna_sustancia}</Text>
            </Paper>
            <Paper withBorder p="sm" radius="md">
              <Text size="xs" c="dimmed">Uso inyectable reciente (P8)</Text>
              <Text size="xl" fw={700} c="red">{resultados.uso_inyectable_reciente}</Text>
            </Paper>
          </SimpleGrid>

          <Group gap="xs">
            {(['bajo', 'moderado', 'alto'] as const).map((nivel) => (
              <StatusBadge tone={TONO_RIESGO_ASSIST[nivel]} key={nivel}>
                {NIVEL_RIESGO_ASSIST_LABELS[nivel]}
              </StatusBadge>
            ))}
          </Group>

          <SgthTable
            records={Object.entries(resultados.por_sustancia).map(([key, d]) => ({ key, ...d }))}
            columns={columns}
            idAccessor="key"
            minHeight={200}
          />
        </Stack>
      )}
    </SgthModal>
  )
}
