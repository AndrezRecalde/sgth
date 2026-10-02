'use client'

import { Stack, Group, SimpleGrid } from '@mantine/core'
import { DataState, SgthModal, SgthTable, StatCard, StatusBadge } from '@/components/ui'
import {
  IconAlertTriangle, IconMoodSmile, IconUsers, IconVaccine,
} from '@tabler/icons-react'
import { useResultadosAssist } from '../hooks/useAssist'
import { NIVEL_RIESGO_ASSIST_LABELS, TONO_RIESGO_ASSIST } from '../schemas/assist.schema'
import { columnasResultadoSustancia } from './resultadoSustancia.columns'

interface Props {
  opened: boolean
  onClose: () => void
  campaniaId: number | null
}

export function ResultadosAssistModal({ opened, onClose, campaniaId }: Props) {
  const { data: resultados, isLoading, error, refetch } = useResultadosAssist(campaniaId)

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Resultados del tamizaje ASSIST"
      size="xl"
    >
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los resultados del tamizaje"
        errorHint="No quiere decir que la campaña no tenga respuestas: no se pudieron consultar."
        onRetry={() => refetch()}
        skeletonRows={5}
        empty={resultados?.total_respuestas === 0}
        emptyProps={{
          icon: IconVaccine,
          title: 'Todavía no hay respuestas',
          description: 'La campaña no ha recibido respuestas. Comparta el enlace público con el personal para que la respondan.',
        }}
      >
        {resultados && resultados.total_respuestas > 0 && (
          <Stack gap="md">
            {/* `StatCard` y no cuatro `Paper` a mano: es el indicador numérico
                del catálogo (regla 06), y escrito a mano salía con otro radio,
                otro padding y sin el icono que llevan los del tablero.

                El tono solo cuando la cifra tiene lectura: cero de riesgo alto
                no es una mala noticia que haya que pintar de rojo. */}
            <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }}>
              <StatCard
                label="Total de respuestas"
                value={resultados.total_respuestas}
                icon={IconUsers}
              />
              <StatCard
                label="No reportan consumo"
                value={resultados.sin_consumo_reportado}
                icon={IconMoodSmile}
                tone={resultados.sin_consumo_reportado > 0 ? 'success' : undefined}
              />
              <StatCard
                label="Riesgo alto en alguna sustancia"
                value={resultados.riesgo_alto_alguna_sustancia}
                icon={IconAlertTriangle}
                tone={resultados.riesgo_alto_alguna_sustancia > 0 ? 'danger' : undefined}
              />
              <StatCard
                label="Uso inyectable reciente"
                value={resultados.uso_inyectable_reciente}
                icon={IconVaccine}
                tone={resultados.uso_inyectable_reciente > 0 ? 'danger' : undefined}
                hint="pregunta 8 del ASSIST"
              />
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
              columns={columnasResultadoSustancia}
              idAccessor="key"
              minHeight={200}
            />
          </Stack>
        )}
      </DataState>
    </SgthModal>
  )
}
