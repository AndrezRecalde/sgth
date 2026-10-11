'use client'

import { Alert, Stack } from '@mantine/core'
import { IconHourglass, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { usePeriodosDePrueba, usePrepararNombramientoDefinitivo } from '../hooks/usePeriodosDePrueba'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import { getPeriodoDePruebaColumns } from './periodosDePrueba.columns'

/**
 * Quién está en período de prueba (LOSEP 17 b.5; diseño, 9.2; fase 3.2): los
 * provisionales de prueba, con la fecha en que terminan sus tres meses.
 *
 * Cumplidos, el comando diario prepara en borrador el nombramiento definitivo,
 * y también se puede preparar desde aquí. Si Talento Humano juzga que no
 * superó la prueba, anula ese borrador y registra la cesación por esa causal.
 */
export function PeriodosDePruebaPanel() {
  const { data: periodos = [], isLoading, error, refetch } = usePeriodosDePrueba()
  const preparar = usePrepararNombramientoDefinitivo()
  const puedePreparar = usePuedePrepararAcciones()

  const columns = getPeriodoDePruebaColumns({
    puedePreparar,
    onPrepararDefinitivo: (p) => preparar.mutate(p.contrato_id),
  })

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Quien gana un concurso entra con nombramiento provisional de prueba. A los
        tres meses recibe el nombramiento definitivo, también si no se le evaluó
        (LOSEP Art. 17 b.5). Si no superó la prueba, anule el borrador del
        definitivo y registre la cesación por esa causal.
      </Alert>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los períodos de prueba"
        onRetry={refetch}
        empty={!periodos.length}
        emptyProps={{
          icon: IconHourglass,
          title: 'Nadie está en período de prueba',
          description: 'Aquí aparecen los provisionales de prueba, con la fecha en que terminan sus tres meses.',
        }}
      >
        <SgthTable records={periodos} columns={columns} idAccessor="contrato_id" minHeight={200} />
      </DataState>
    </Stack>
  )
}
