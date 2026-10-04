'use client'

import { useState } from 'react'
import { Accordion, Alert, Stack } from '@mantine/core'
import { IconBriefcase, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SectionCard } from '@/components/ui'
import { useActividadLaboral } from '../../hooks/useActividadLaboral'
import { ReprogramarPlazoModal } from '../ReprogramarPlazoModal'
import { VinculoLaboralItem } from '../VinculoLaboralItem'
import type { VinculoConActividad } from '../../services/actividadLaboralService'

interface Props {
  servidorId: number
}

/**
 * Actividad laboral: cada vínculo con las acciones ocurridas sobre él.
 *
 * No hay botones para crear ni cerrar contratos: un vínculo nace de una acción
 * de personal de ingreso y muere con una de cesación. Permitirlo aquí sería la
 * puerta trasera que deja contratos sin acción que los respalde.
 */
export function LaboralTab({ servidorId }: Props) {
  const { data: vinculos = [], isLoading, error } = useActividadLaboral(servidorId)

  /** El vínculo abierto para reprogramar; null = modal cerrado. */
  const [reprogramando, setReprogramando] = useState<VinculoConActividad | null>(null)

  return (
    <Stack gap="md">
      <SectionCard title="Vínculos laborales">
        {vinculos.length > 0 && (
          <Alert
            variant="light"
            color="ocean"
            icon={<IconInfoCircle size={16} />}
            mb="md"
          >
            Cada vínculo conserva su número de contrato original. Traspasos,
            comisiones y sanciones no crean uno nuevo: se registran sobre el
            mismo, y se ven al desplegarlo.
          </Alert>
        )}

        <DataState
          loading={isLoading}
          error={error}
          empty={vinculos.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconBriefcase,
            title: 'Sin vínculos registrados',
            description: 'El vínculo laboral se crea al aprobar una acción de personal de Ingreso y Vinculación.',
          }}
        >
          <Accordion variant="separated" defaultValue={String(vinculos[0]?.contrato.id)}>
            {vinculos.map((v) => (
              <VinculoLaboralItem key={v.contrato.id} vinculo={v} onReprogramar={setReprogramando} />
            ))}
          </Accordion>
        </DataState>
      </SectionCard>

      <ReprogramarPlazoModal
        // Se monta de nuevo por vínculo: arranca con SU vencimiento actual.
        key={reprogramando?.contrato.id ?? 'cerrado'}
        opened={reprogramando !== null}
        onClose={() => setReprogramando(null)}
        servidorId={servidorId}
        contrato={reprogramando?.contrato ?? null}
        // Un reemplazo no se prorroga más allá de la ausencia que cubre.
        hastaReemplazo={reprogramando?.reemplaza_a?.hasta ?? null}
      />
    </Stack>
  )
}
