'use client'

import { Alert, SimpleGrid, Text } from '@mantine/core'
import {
  IconAlertTriangle,
  IconBeach,
  IconCalendarStats,
  IconHourglassHigh,
  IconSum,
} from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, StatCard } from '@/components/ui'
import { usePeriodosVacaciones } from '../hooks/usePeriodosVacaciones'
import { getPeriodosVacacionesColumns } from './periodosVacaciones.columns'

interface Props {
  servidorId:     number
  /** Días de las solicitudes pendientes: todavía no salen del saldo. */
  diasPorAprobar: number
}

/**
 * El saldo y los períodos del propio servidor, en «Mis vacaciones».
 *
 * Es el mismo resumen que consulta Talento Humano en Períodos de vacaciones
 * (`VacacionPolicy::verSaldo` deja ver el propio), sin recalcular: eso es de
 * quien gestiona las vacaciones.
 */
export function MiSaldoVacaciones({ servidorId, diasPorAprobar }: Props) {
  const { data: resumen, isLoading, error, refetch } = usePeriodosVacaciones(servidorId)

  const periodos = resumen?.periodos ?? []
  const saldo    = Number(resumen?.saldo_total ?? 0)
  const tope     = resumen?.tope ?? null
  const alerta   = resumen?.alerta_limite ?? false

  const columnas = getPeriodosVacacionesColumns({
    tope,
    puedeRecalcular: false,
    onRecalcular:    () => undefined,
  }).filter(c => c.accessor !== 'acciones')

  return (
    <>
      <SimpleGrid cols={{ base: 1, xs: 3 }} spacing="md">
        <StatCard
          label="Saldo disponible"
          value={saldo.toFixed(1)}
          icon={IconSum}
          tone={alerta ? 'warning' : undefined}
          hint={tope !== null ? `días, de un tope de ${tope.toFixed(0)}` : 'días'}
          loading={isLoading}
        />
        <StatCard
          label="Por aprobar"
          value={diasPorAprobar}
          icon={IconHourglassHigh}
          hint="días que aún no se descuentan del saldo"
        />
        <StatCard
          label="Gozados por vacaciones"
          value={Number(resumen?.total_vacaciones_aprobadas ?? 0).toFixed(1)}
          icon={IconBeach}
          hint="días, en todos sus períodos"
          loading={isLoading}
        />
      </SimpleGrid>

      {alerta && (
        <Alert icon={<IconAlertTriangle size={16} />} color="amber" variant="light">
          <Text size="sm">
            Su saldo se acerca al tope de acumulación o lo pasa. Conviene que
            goce vacaciones pronto: lo que exceda el tope lo puede dar por
            vencido Talento Humano.
          </Text>
        </Alert>
      )}

      <SectionCard
        title="Mis períodos"
        description="Los días que le genera cada año de servicio y cuánto le queda de cada uno."
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={!periodos.length}
          errorTitle="No se pudo cargar su saldo de vacaciones"
          errorHint="No quiere decir que no tenga días: no se pudieron consultar."
          onRetry={() => void refetch()}
          skeletonRows={3}
          emptyProps={{
            icon: IconCalendarStats,
            title: 'Todavía no tiene períodos de vacaciones',
            description:
              'Talento Humano genera el período de cada año. Hasta entonces no hay saldo ' +
              'del que descontar sus vacaciones.',
          }}
        >
          <SgthTable records={periodos} columns={columnas} minHeight={120} />
        </DataState>
      </SectionCard>
    </>
  )
}
