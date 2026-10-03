'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Select, Stack } from '@mantine/core'
import { IconArrowRight } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTableroSaludOcupacional } from '@/features/dispensario/hooks/useTableroSaludOcupacional'
import { useCobertura } from '@/features/dispensario/hooks/useCoberturaCertificacion'
import { TableroSsoBandeja } from '@/features/dispensario/components/TableroSsoBandeja'
import { TableroSsoEvaluaciones } from '@/features/dispensario/components/TableroSsoEvaluaciones'
import { ResumenCoberturaTarjetas } from '@/features/dispensario/components/ResumenCoberturaTarjetas'
import { DataState, PageHeader, PageShell, SectionCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'

const ANIO_ACTUAL = new Date().getFullYear()
const ANIOS = Array.from({ length: 5 }, (_, i) => String(ANIO_ACTUAL - i))

/**
 * El tablero de salud ocupacional: la bandeja en cifras, lo emitido en el año
 * y la cobertura de las evaluaciones periódicas.
 */
export function TableroSsoView() {
  const router    = useRouter()
  const contained = useContainedInput('sm')
  const [anio, setAnio] = useState(String(ANIO_ACTUAL))

  const { data, isLoading, error } = useTableroSaludOcupacional(Number(anio))
  // Sin filtros: el resumen es de toda la plantilla activa.
  const cobertura = useCobertura()

  return (
    <PageShell>
      <PageHeader
        title="Tablero de salud ocupacional"
        description="Lo que hay por atender hoy y lo emitido en el año"
        actions={
          <Select
            label="Año"
            data={ANIOS}
            allowDeselect={false}
            w={120}
            {...contained}
            value={anio}
            onChange={(v) => setAnio(v ?? String(ANIO_ACTUAL))}
          />
        }
      />

      <DataState loading={isLoading} error={error} skeletonRows={4}>
        <Stack gap="lg">
          <TableroSsoBandeja datos={data} cargando={isLoading} />
          <TableroSsoEvaluaciones datos={data} />
        </Stack>
      </DataState>

      <SectionCard
        title="Cobertura de evaluaciones periódicas"
        description="Toda la plantilla activa, hoy"
        actions={
          <Button
            variant="subtle"
            rightSection={<IconArrowRight size={14} />}
            onClick={() => router.push(ROUTES.SALUD.SSO_COBERTURA)}
          >
            Ver detalle
          </Button>
        }
      >
        <ResumenCoberturaTarjetas resumen={cobertura.data?.resumen} cargando={cobertura.isLoading} />
      </SectionCard>
    </PageShell>
  )
}
