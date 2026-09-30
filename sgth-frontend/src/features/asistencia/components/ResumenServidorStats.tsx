'use client'

import { SimpleGrid } from '@mantine/core'
import {
  IconBeach,
  IconCalendarStats,
  IconClockHour4,
  IconSum,
} from '@tabler/icons-react'
import { SectionCard, StatCard } from '@/components/ui'
import type { PeriodoVacacion, ResumenPeriodos } from '@/types/api'

interface Props {
  periodos: PeriodoVacacion[]
  resumen: ResumenPeriodos
}

/**
 * Los totales del servidor, bajo su tabla de períodos.
 *
 * Eran seis bloques de `Stack` + `Text` armados a mano, con los colores
 * repartidos sin significado —«Días por permisos» iba en ámbar, que en este
 * sistema quiere decir advertencia—. El catálogo tiene `StatCard` justo para
 * esto, y su `tone` se reserva para cuando el número TIENE una lectura buena o
 * mala: aquí solo el saldo la tiene, cuando se acerca al tope.
 */
export function ResumenServidorStats({ periodos, resumen }: Props) {
  const saldoTotal    = Number(resumen.saldo_total ?? 0)
  const alertaLimite  = resumen.alerta_limite ?? false
  const tope          = resumen.tope ?? null

  const totalGenerados = periodos.reduce((acc, p) => acc + Number(p.dias_generados), 0)
  const totalGozados   = periodos.reduce((acc, p) => acc + Number(p.dias_utilizados), 0)

  return (
    <SectionCard title="Resumen del servidor">
      <SimpleGrid cols={{ base: 1, xs: 2, md: 3, lg: 5 }} spacing="md">
        <StatCard
          label="Períodos"
          value={periodos.length}
          icon={IconCalendarStats}
          hint="registrados"
        />
        <StatCard
          label="Saldo total"
          value={saldoTotal.toFixed(1)}
          icon={IconSum}
          tone={alertaLimite ? 'warning' : undefined}
          hint={tope !== null ? `de un tope de ${tope.toFixed(0)}` : undefined}
        />
        <StatCard
          label="Días generados"
          value={totalGenerados.toFixed(1)}
          icon={IconCalendarStats}
          hint="en toda su historia"
        />
        <StatCard
          label="Por vacaciones"
          value={Number(resumen.total_vacaciones_aprobadas ?? 0).toFixed(1)}
          icon={IconBeach}
          hint={`de ${totalGozados.toFixed(1)} días gozados`}
        />
        <StatCard
          label="Por permisos"
          value={Number(resumen.total_permisos_personales ?? 0).toFixed(2)}
          icon={IconClockHour4}
          hint="permisos personales"
        />
      </SimpleGrid>
    </SectionCard>
  )
}
