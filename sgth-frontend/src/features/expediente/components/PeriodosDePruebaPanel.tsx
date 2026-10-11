'use client'

import React from 'react'
import { Alert, Stack } from '@mantine/core'
import { IconHourglass, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { usePeriodosDePrueba, usePrepararNombramientoDefinitivo } from '../hooks/usePeriodosDePrueba'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import type { PeriodoDePrueba } from '../services/periodoDePruebaService'
import { getPeriodoDePruebaColumns } from './periodosDePrueba.columns'
import { ReintegroAscensoModal } from './ReintegroAscensoModal'

/**
 * Quién está en período de prueba (LOSEP 17 b.5; diseño, 9.2; fases 3.2 y
 * 3.3): quien ingresó por concurso, tres meses, y quien ascendió, seis, con el
 * puesto que se le reserva.
 *
 * Cumplida la prueba, el comando diario prepara en borrador el nombramiento
 * definitivo, y también se puede preparar desde aquí. Si quien ingresó no la
 * superó, Talento Humano anula ese borrador y registra la cesación; el
 * ascendido no cesa: se le prepara desde aquí el reintegro a su puesto anterior.
 */
export function PeriodosDePruebaPanel() {
  const { data: periodos = [], isLoading, error, refetch } = usePeriodosDePrueba()
  const preparar = usePrepararNombramientoDefinitivo()
  const puedePreparar = usePuedePrepararAcciones()
  const [aReintegrar, setAReintegrar] = React.useState<PeriodoDePrueba | null>(null)

  const columns = getPeriodoDePruebaColumns({
    puedePreparar,
    onPrepararDefinitivo: (p) => preparar.mutate(p.contrato_id),
    onReintegrar: setAReintegrar,
  })

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Quien gana un concurso entra con nombramiento provisional de prueba: tres
        meses si ingresa y seis si asciende, con su puesto anterior reservado. Al
        terminar recibe el nombramiento definitivo, también si no se le evaluó
        (LOSEP Art. 17 b.5). Si quien ingresó no superó la prueba, anule el
        borrador del definitivo y registre la cesación por esa causal; el
        ascendido vuelve a su puesto con el reintegro.
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
          description: 'Aquí aparecen los provisionales de prueba y los ascendidos, con la fecha en que termina su prueba.',
        }}
      >
        <SgthTable records={periodos} columns={columns} idAccessor="contrato_id" minHeight={200} />
      </DataState>

      <ReintegroAscensoModal
        opened={!!aReintegrar}
        onClose={() => setAReintegrar(null)}
        periodo={aReintegrar}
      />
    </Stack>
  )
}
