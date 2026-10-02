'use client'

import { Box, Group, SimpleGrid, Text } from '@mantine/core'
import {
  IconClipboardList, IconClock, IconHelmet, IconSchool, IconShieldCheck,
} from '@tabler/icons-react'
import { DataState, StatCard } from '@/components/ui'
import { useIndicadoresProactivos } from '../hooks/useIndicadoresSso'
import { AvisoAlcance } from './AvisoAlcance'

interface Props {
  periodo: string
  unidadId?: number
}

/** Inspecciones, capacitaciones y cobertura de EPP del período. */
export function IndicadoresProactivosPanel({ periodo, unidadId }: Props) {
  const { data: proactivos, isLoading, error, refetch } = useIndicadoresProactivos({
    periodo,
    unidad_administrativa_id: unidadId,
  })

  return (
    <Box>
      <Group gap="xs" mb="sm">
        <IconShieldCheck size={18} />
        <Text fw={600}>Índices proactivos</Text>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron calcular los índices proactivos"
        errorHint="No quiere decir que no haya inspecciones ni capacitaciones en el período: no se pudo consultar."
        onRetry={() => refetch()}
        skeletonRows={2}
      >
        {proactivos && (
          <>
            {/* De los tres indicadores, dos filtran por unidad y las
                capacitaciones no pueden: `capacitaciones_sso` no tiene
                columna de unidad. Antes eso quedaba implícito y la
                cifra institucional se leía como si fuera de la unidad. */}
            <AvisoAlcance
              alcances={proactivos.alcances}
              etiquetas={{
                inspecciones: 'Inspecciones',
                capacitaciones: 'Capacitaciones',
                cobertura_epp: 'Cobertura EPP',
              }}
            />
            <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md" mt="md">
              <StatCard label="Inspecciones realizadas" value={proactivos.inspecciones_realizadas} icon={IconClipboardList} />
              <StatCard label="Capacitaciones realizadas" value={proactivos.capacitaciones_realizadas} icon={IconSchool} />
              <StatCard label="Horas de capacitación" value={proactivos.horas_capacitacion_total} icon={IconClock} />
              <StatCard
                label="Cobertura EPP"
                value={proactivos.cobertura_epp.porcentaje !== null ? `${proactivos.cobertura_epp.porcentaje}%` : '—'}
                icon={IconHelmet}
                hint={proactivos.cobertura_epp.total_puestos_con_epp_requerido > 0
                  ? `${proactivos.cobertura_epp.puestos_con_entrega_en_periodo} de ${proactivos.cobertura_epp.total_puestos_con_epp_requerido} puestos`
                  : undefined}
              />
            </SimpleGrid>
            <Text size="xs" c="dimmed" mt="xs">
              El sistema no distingue actividades planificadas de realizadas; se reportan los conteos reales
              registrados en el período.
            </Text>
          </>
        )}
      </DataState>
    </Box>
  )
}
