'use client'

import { Box, Button, Group, SimpleGrid, Text } from '@mantine/core'
import { IconAlertTriangle, IconBed, IconClock, IconGauge } from '@tabler/icons-react'
import { DataState, StatCard } from '@/components/ui'
import { useIndicadoresReactivos } from '../hooks/useIndicadoresSso'

interface Props {
  periodo: string
  unidadId?: number
  puedeGestionar: boolean
  onCargarHoras: () => void
}

/** Los tres índices del CD 513 y los datos con los que se calculan. */
export function IndicadoresReactivosPanel({ periodo, unidadId, puedeGestionar, onCargarHoras }: Props) {
  const { data: reactivos, isLoading, error, refetch } = useIndicadoresReactivos({
    periodo,
    unidad_administrativa_id: unidadId,
  })

  return (
    <Box>
      <Group gap="xs" mb="sm">
        <IconGauge size={18} />
        <Text fw={600}>Índices reactivos — CD 513</Text>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron calcular los índices reactivos"
        errorHint="No quiere decir que el período no tenga accidentes: no se pudo consultar."
        onRetry={() => refetch()}
        skeletonRows={2}
        // El backend marca `sin_datos` cuando falta el denominador: es el
        // estado vacío de esta pantalla, y su mensaje ya dice qué cargar.
        empty={!!reactivos?.sin_datos}
        emptyProps={{
          icon: IconClock,
          title: 'Faltan las horas trabajadas del período',
          description: reactivos?.mensaje,
          action: puedeGestionar ? (
            <Button variant="light" leftSection={<IconClock size={16} />} onClick={onCargarHoras}>
              Cargar horas trabajadas
            </Button>
          ) : undefined,
        }}
      >
        {reactivos && !reactivos.sin_datos && (
          <>
            <SimpleGrid cols={{ base: 2, sm: 3 }} spacing="md">
              {/* Los tres índices no llevan tono: su lectura buena o mala
                  depende de la serie histórica del GAD, que el sistema
                  todavía no guarda. Un rojo fijo diría que 15,71 es malo
                  sin nada contra qué compararlo. */}
              <StatCard label="Índice de frecuencia (IF)" value={reactivos.indice_frecuencia ?? '—'} icon={IconGauge} />
              <StatCard label="Índice de gravedad (IG)" value={reactivos.indice_gravedad ?? '—'} icon={IconGauge} />
              <StatCard label="Tasa de riesgo (TR)" value={reactivos.tasa_riesgo ?? '—'} icon={IconGauge} hint="días perdidos por lesión" />
              <StatCard label="Lesiones (accidentes)" value={reactivos.numero_lesiones} icon={IconAlertTriangle} />
              <StatCard label="Días perdidos" value={reactivos.dias_perdidos} icon={IconBed} />
              <StatCard label="Horas trabajadas" value={reactivos.horas_trabajadas.toLocaleString()} icon={IconClock} />
            </SimpleGrid>

            {/* El denominador puede venir del período exacto o de la suma
                de sus meses, y del total institucional o de la suma de
                unidades: sin decirlo, el índice no se puede auditar. */}
            {reactivos.horas_trabajadas_detalle && (
              <Text size="xs" c="dimmed" mt="xs">
                {reactivos.horas_trabajadas_detalle}
              </Text>
            )}

            <Text size="xs" c="dimmed" mt={4}>
              Fórmulas CD 513 (IESS): IF = (lesiones × 200000) / horas; IG = (días perdidos × 200000) / horas;
              TR = IG / IF. Pendientes de confirmación legal directa por Talento Humano contra el reglamento oficial.
            </Text>
          </>
        )}
      </DataState>
    </Box>
  )
}
