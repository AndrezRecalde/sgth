'use client'

import { useState } from 'react'
import { Alert, Stack, Text } from '@mantine/core'
import { IconChecks, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SectionHeading, SgthTable, confirmar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { usePorRevisarTipo, useRegularizacionTipoMutations } from '../hooks/useRegularizacionTipo'
import type { ContratoPorRegularizar } from '../services/regularizacionTipoService'
import { getContratosPorRegularizarColumns, getPuestosPorRegularizarColumns } from './regularizacionTipo.columns'
import { ReubicarContratoModal } from './ReubicarContratoModal'

/**
 * «Por revisar»: la regularización del tipo de puesto
 * (docs/diseno-manual-de-puestos.md, P9, PR 4). Todos los puestos nacieron de
 * plantilla, y el sistema propone dos cosas que Talento Humano confirma una a
 * una: nada cambia solo.
 */
export function RegularizacionTipoTab() {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-puestos')
  // Reubicar cambia el vínculo de un servidor: es del expediente.
  const puedeReubicar = puedeGestionar && hasPermiso('gestionar-expediente')

  const { data, isLoading, error } = usePorRevisarTipo(puedeGestionar)
  const { pasarAContrato } = useRegularizacionTipoMutations()
  const [aReubicar, setAReubicar] = useState<ContratoPorRegularizar | null>(null)

  const puestos = data?.puestos ?? []
  const contratos = data?.contratos ?? []

  return (
    <Stack gap="lg">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Un puesto de plantilla es del distributivo: tiene plazas y cuenta en las vacantes. Los ocasionales y
        los de servicios profesionales van en puestos de contrato, salvo que reemplacen a un servidor ausente.
        Revise cada caso: el sistema no cambia nada por su cuenta.
      </Alert>

      <div>
        <SectionHeading title="Puestos que parecen de contrato" />
        <Text size="xs" c="dimmed" mb="xs">
          Son de plantilla, pero solo los ocupan ocasionales o profesionales. Si el puesto existe para
          ellos, páselo a contrato: deja de tener plazas y de contarse como vacante. Si es del
          distributivo, reubique a sus ocupantes más abajo.
        </Text>
        <DataState
          loading={isLoading}
          error={error}
          empty={!puestos.length}
          emptyProps={{ icon: IconChecks, title: 'Nada por revisar', description: 'Ningún puesto de plantilla está ocupado solo por contratos.' }}
        >
          <SgthTable
            records={puestos}
            columns={getPuestosPorRegularizarColumns({
              puedeGestionar,
              alPasarAContrato: (p) => confirmar({
                title: 'Pasar a contrato',
                message: <>«<b>{p.cargo}</b>» dejará de tener plazas y de contarse en las vacantes de la Plantilla. Sus ocupantes siguen igual.</>,
                confirmLabel: 'Pasar a contrato',
                onConfirm: () => pasarAContrato.mutate(p.id),
              }),
            })}
            minHeight={120}
          />
        </DataState>
      </div>

      <div>
        <SectionHeading title="Contratos en un puesto de plantilla" />
        <Text size="xs" c="dimmed" mb="xs">
          Ocasionales y profesionales que no reemplazan a nadie. Se reubican en un puesto de contrato de su
          misma unidad, sin acción de personal: no cambia su situación.
        </Text>
        <DataState
          loading={isLoading}
          error={error}
          empty={!contratos.length}
          emptyProps={{ icon: IconChecks, title: 'Nada por revisar', description: 'Ningún ocasional ni profesional está en un puesto de plantilla.' }}
        >
          <SgthTable
            records={contratos}
            columns={getContratosPorRegularizarColumns({ puedeReubicar, alReubicar: setAReubicar })}
            minHeight={120}
          />
        </DataState>
      </div>

      <ReubicarContratoModal contrato={aReubicar} onClose={() => setAReubicar(null)} />
    </Stack>
  )
}
