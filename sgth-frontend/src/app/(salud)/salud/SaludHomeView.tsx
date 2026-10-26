'use client'

import { useState } from 'react'
import { Select } from '@mantine/core'
import { PageHeader, PageShell } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { mesActual, mesesRecientes, rangoDeMes } from '@/features/dispensario/utils/periodoTablero'
import { TableroDispensario } from
  '@/features/dispensario/components/TableroDispensario'

const MESES = mesesRecientes(12)

/**
 * El tablero del Dispensario, que es de gestión: el backend solo se lo sirve a
 * la administración del dispensario, y el menú solo a ella se lo ofrece.
 *
 * Al resto del personal le quedaba en blanco, y esta pantalla lo mandaba a la
 * suya según su rol. Ya no hace falta: el shell lleva a quien entra al
 * Dispensario sin tener el tablero a su primera pantalla del menú —el médico a
 * Consultas, la enfermera a Enfermería— (`GuardaDePantalla`).
 */
export function SaludHomeView() {
  const contained = useContainedInput('sm')
  // El tablero mira un mes, el actual por defecto. Antes no había selector:
  // el día 1 todo salía en ceros y un mes cerrado no se podía consultar.
  const [mes, setMes] = useState(mesActual)

  return (
    <PageShell>
      <PageHeader
        title="Dispensario Médico"
        description="Atenciones, pacientes, farmacia e inventario del mes elegido"
        actions={
          <Select
            label="Mes"
            data={MESES}
            allowDeselect={false}
            w={200}
            {...contained}
            value={mes}
            onChange={(v) => setMes(v ?? mesActual())}
          />
        }
      />
      <TableroDispensario periodo={rangoDeMes(mes)} />
    </PageShell>
  )
}
