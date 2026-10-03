'use client'

import { Stack } from '@mantine/core'
import { DataState } from '@/components/ui'
import { useKpisDispensario } from '../hooks/useKpis'
import { usePanoramaDispensario } from '../hooks/usePanoramaDispensario'
import { useAuthStore } from '@/store/auth.store'
import type { PeriodoTablero } from '../utils/periodoTablero'
import { TableroCifras } from './tablero/TableroCifras'
import { TableroClinico } from './tablero/TableroClinico'
import { TableroFarmacia } from './tablero/TableroFarmacia'
import { TableroFlujo } from './tablero/TableroFlujo'
import { TableroAreas } from './tablero/TableroAreas'
import { TableroTendencia } from './tablero/TableroTendencia'

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
  const panorama = usePanoramaDispensario(periodo, puedeVerlo)

  if (!puedeVerlo) return null

  return (
    <DataState loading={isLoading} error={error} skeletonRows={4}>
      <Stack gap="lg">
        {/* Primero lo de hoy: es lo que la jefatura gestiona a diario. */}
        <TableroFlujo panorama={panorama.data} cargando={panorama.isLoading} />
        <TableroCifras kpis={kpis} />
        <TableroTendencia tendencia={panorama.data?.tendencia} />
        <TableroClinico kpis={kpis} />
        <TableroAreas panorama={panorama.data} />
        <TableroFarmacia kpis={kpis} />
      </Stack>
    </DataState>
  )
}
