'use client'

import { useState } from 'react'
import { Center, Pagination, Stack } from '@mantine/core'
import { IconFlask } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell } from '@/components/ui'
import { useMisExamenes } from '../hooks/useMisExamenes'
import { POR_PAGINA_MIS_EXAMENES, type MiOrdenExamen } from '../services/misExamenesService'
import { EnviarResultadoModal } from './EnviarResultadoModal'
import { MiOrdenExamenCard } from './MiOrdenExamenCard'

/**
 * «Mis exámenes» del Portal (docs/diseno-examenes-medicos.md, 5.7): las órdenes
 * que le dio el Dispensario, el impreso por si lo perdió, y enviar lo que le
 * dio el laboratorio para que un médico lo valide (D2, D9).
 */
export function MisExamenesView() {
  const [page, setPage] = useState(1)
  const [enviarA, setEnviarA] = useState<MiOrdenExamen | null>(null)
  const { data, isLoading, error, refetch } = useMisExamenes(page)
  const ordenes = data?.data ?? []
  const paginas = Math.ceil((data?.total ?? 0) / POR_PAGINA_MIS_EXAMENES)

  return (
    <PageShell>
      <PageHeader
        title="Mis exámenes médicos"
        description="Las órdenes de exámenes que le dio el Dispensario. Si perdió el papel, descárguelo; cuando tenga el resultado del laboratorio, envíelo desde aquí."
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar sus órdenes de exámenes"
        onRetry={refetch}
        empty={!ordenes.length}
        emptyProps={{
          icon: IconFlask,
          title: 'No tiene órdenes de exámenes',
          description: 'Cuando un médico del Dispensario le pida exámenes, la orden aparecerá aquí.',
        }}
        page={page}
      >
        <Stack gap="md">
          {ordenes.map((o) => <MiOrdenExamenCard key={o.id} orden={o} onEnviar={setEnviarA} />)}
        </Stack>
        {paginas > 1 && (
          <Center mt="md">
            <Pagination total={paginas} value={page} onChange={setPage} />
          </Center>
        )}
      </DataState>

      <EnviarResultadoModal orden={enviarA} onClose={() => setEnviarA(null)} />
    </PageShell>
  )
}
