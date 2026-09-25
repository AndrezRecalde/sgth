'use client'

import { Stack, Text, Group } from '@mantine/core'
import { DataState, SgthModal, SgthTable, StatusBadge } from '@/components/ui'
import { IconMoodSmile } from '@tabler/icons-react'
import { useResultadosPsicosociales } from '../hooks/usePsicosocial'
import { NIVEL_RIESGO_PSICOSOCIAL_LABELS, TONO_RIESGO_PSICOSOCIAL } from '../schemas/psicosocial.schema'
import type { ResultadoDimensionAgregado } from '../services/psicosocialService'
import type { DataTableColumn } from 'mantine-datatable'

type FilaDimension = ResultadoDimensionAgregado & { key: string }

interface Props {
  opened: boolean
  onClose: () => void
  campaniaId: number | null
}

export function ResultadosPsicosocialesModal({ opened, onClose, campaniaId }: Props) {
  const { data: resultados, isLoading, error, refetch } = useResultadosPsicosociales(campaniaId)

  const columns: DataTableColumn<FilaDimension>[] = [
    { accessor: 'etiqueta', title: 'Dimensión' },
    { accessor: 'bajo', title: 'Bajo', textAlign: 'center', width: 90 },
    { accessor: 'medio', title: 'Medio', textAlign: 'center', width: 90 },
    { accessor: 'alto', title: 'Alto', textAlign: 'center', width: 90 },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Resultados de la evaluación psicosocial"
      size="lg"
    >
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los resultados de la evaluación"
        errorHint="No quiere decir que la campaña no tenga respuestas: no se pudieron consultar."
        onRetry={() => refetch()}
        skeletonRows={5}
        empty={resultados?.total_respuestas === 0}
        emptyProps={{
          icon: IconMoodSmile,
          title: 'Todavía no hay respuestas',
          description: 'La campaña no ha recibido respuestas. Comparta el enlace público con el personal para que la respondan.',
        }}
      >
        {resultados && resultados.total_respuestas > 0 && (
          <Stack gap="md">
            <Text size="sm" c="dimmed">
              Total de respuestas: <Text span fw={600}>{resultados.total_respuestas}</Text>
            </Text>

            <Stack gap={4}>
              <Text size="sm" fw={600}>Resultado global</Text>
              <Group gap="xs">
                {(['bajo', 'medio', 'alto'] as const).map((nivel) => (
                  <StatusBadge tone={TONO_RIESGO_PSICOSOCIAL[nivel]} key={nivel}>
                    {NIVEL_RIESGO_PSICOSOCIAL_LABELS[nivel]}: {resultados.global[nivel]}
                  </StatusBadge>
                ))}
              </Group>
            </Stack>

            <SgthTable
              records={Object.entries(resultados.por_dimension).map(([key, d]) => ({ key, ...d }))}
              columns={columns}
              idAccessor="key"
              minHeight={120}
            />
          </Stack>
        )}
      </DataState>
    </SgthModal>
  )
}
