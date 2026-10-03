'use client'

import { SimpleGrid } from '@mantine/core'
import { IconChartBar, IconDental, IconStethoscope, IconUsers } from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import type { KpisDispensario } from '../../services/kpisService'
import { etiquetaMes } from '../../utils/periodoTablero'

interface Props {
  kpis?: KpisDispensario
}

/** «3 más que agosto de 2026», «igual que agosto de 2026». */
function contraAnterior(actual: number, anterior: number, referencia: string): string {
  const diferencia = actual - anterior
  if (diferencia === 0) return `Igual que ${referencia}`
  return `${Math.abs(diferencia)} ${diferencia > 0 ? 'más' : 'menos'} que ${referencia}`
}

/** Las cuatro cifras de cabecera del tablero del Dispensario. */
export function TableroCifras({ kpis }: Props) {
  const general = kpis?.atenciones_por_especialidad.medicina_general ?? 0
  const odonto  = kpis?.atenciones_por_especialidad.odontologia ?? 0
  const total   = kpis?.atenciones ?? 0
  const p       = kpis?.pacientes

  // Si se pidió un mes, «agosto de 2026»; si no, «el período anterior».
  const anterior = kpis?.periodo_anterior.desde.endsWith('-01')
    ? etiquetaMes(kpis.periodo_anterior.desde.slice(0, 7))
    : 'el período anterior'

  const porcentaje = (n: number) =>
    total > 0 ? `${Math.round((n / total) * 100)}% del período` : 'Sin atenciones en el período'

  return (
    <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }} spacing="md">
      <StatCard
        label="Atenciones"
        value={total}
        icon={IconChartBar}
        hint={kpis ? contraAnterior(total, kpis.atenciones_periodo_anterior, anterior) : undefined}
      />
      <StatCard label="Medicina general" value={general} icon={IconStethoscope} hint={porcentaje(general)} />
      <StatCard label="Odontología" value={odonto} icon={IconDental} hint={porcentaje(odonto)} />
      {/* Personas distintas: antes contaba consultas, y quien volvía tres
          veces contaba tres. */}
      <StatCard
        label="Pacientes"
        value={p?.distintos ?? 0}
        icon={IconUsers}
        hint={p
          ? `${p.titulares} titulares · ${p.carga_familiar} carga familiar${p.candidatos ? ` · ${p.candidatos} candidatos` : ''}`
          : undefined}
      />
    </SimpleGrid>
  )
}
