'use client'

import { Stack } from '@mantine/core'
import { DataState } from '@/components/ui'
import { useKpisDispensario } from '../hooks/useKpis'
import { useAuthStore } from '@/store/auth.store'
import type { PeriodoTablero } from '../utils/periodoTablero'
import { TableroCifras } from './tablero/TableroCifras'
import { TableroClinico } from './tablero/TableroClinico'
import { TableroFarmacia } from './tablero/TableroFarmacia'

interface Props {
  periodo: PeriodoTablero
}

/**
 * Las cifras del Dispensario en el período elegido.
 *
 * Eran siempre del mes en curso: el día 1 el tablero salía en ceros y no se
 * podía mirar un mes cerrado.
 */
export function TableroDispensario({ periodo }: Props) {
  // El endpoint es de la administración del dispensario y de la máxima
  // autoridad. A `/salud` llega cualquiera del módulo, así que sin esta
  // comprobación un médico o una enfermera se encontrarían la pantalla de
  // inicio presidida por un error de permisos.
  const hasRole = useAuthStore((s) => s.hasRole)
  const puedeVerlo = hasRole('admin-dispensario') || hasRole('maxima-autoridad')

  const { data: kpis, isLoading, error } = useKpisDispensario(periodo, puedeVerlo)

  if (!puedeVerlo) return null

  return (
    <DataState loading={isLoading} error={error} skeletonRows={4}>
      <Stack gap="lg">
        <TableroCifras kpis={kpis} />
        <TableroClinico kpis={kpis} />
        <TableroFarmacia kpis={kpis} />
      </Stack>
    </DataState>
  )
}
