'use client'

import { Modal, Stack, Text, Badge, Group, Skeleton, Alert } from '@mantine/core'
import { IconAlertCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
import { useResultadosPsicosociales } from '../hooks/usePsicosocial'
import { NIVEL_RIESGO_PSICOSOCIAL_COLORS, NIVEL_RIESGO_PSICOSOCIAL_LABELS } from '../schemas/psicosocial.schema'
import type { ResultadoDimensionAgregado } from '../services/psicosocialService'
import type { DataTableColumn } from 'mantine-datatable'

type FilaDimension = ResultadoDimensionAgregado & { key: string }

interface Props {
  opened: boolean
  onClose: () => void
  campaniaId: number | null
}

export function ResultadosPsicosocialesModal({ opened, onClose, campaniaId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { data: resultados, isLoading } = useResultadosPsicosociales(campaniaId)

  const columns: DataTableColumn<FilaDimension>[] = [
    { accessor: 'etiqueta', title: 'Dimensión' },
    { accessor: 'bajo', title: 'Bajo', textAlign: 'center', width: 90 },
    { accessor: 'medio', title: 'Medio', textAlign: 'center', width: 90 },
    { accessor: 'alto', title: 'Alto', textAlign: 'center', width: 90 },
  ]

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Resultados de la evaluación psicosocial"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      {isLoading && <Skeleton height={300} radius="md" />}

      {!isLoading && resultados && resultados.total_respuestas === 0 && (
        <Alert icon={<IconAlertCircle size={18} />} color="blue" variant="light">
          Todavía no se han registrado respuestas para esta campaña.
        </Alert>
      )}

      {!isLoading && resultados && resultados.total_respuestas > 0 && (
        <Stack gap="md">
          <Text size="sm" c="dimmed">
            Total de respuestas: <Text span fw={600}>{resultados.total_respuestas}</Text>
          </Text>

          <Stack gap={4}>
            <Text size="sm" fw={600}>Resultado global</Text>
            <Group gap="xs">
              {(['bajo', 'medio', 'alto'] as const).map((nivel) => (
                <Badge key={nivel} color={NIVEL_RIESGO_PSICOSOCIAL_COLORS[nivel]} variant="light">
                  {NIVEL_RIESGO_PSICOSOCIAL_LABELS[nivel]}: {resultados.global[nivel]}
                </Badge>
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
    </Modal>
  )
}
