'use client'

import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import { Stack } from '@mantine/core'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { NominaToolbar } from '@/features/nomina/components/NominaToolbar'
import { getNominaColumns } from '@/features/nomina/components/nomina.columns'
import { useNominas } from '@/features/nomina/hooks/useNominas'
import { useNominaMutations } from '@/features/nomina/hooks/useNominaMutations'
import { IconReportMoney } from '@tabler/icons-react'
import type { Nomina } from '@/types/api'

export function NominaView() {
  const [filtroEstado, setFiltroEstado] = useState<string | null>(null)
  const { data: nominas = [], isLoading } = useNominas()
  const { cerrar } = useNominaMutations()

  const lista = (nominas as Nomina[]).filter(n =>
    filtroEstado ? n.estado === filtroEstado : true
  )

  const columns = getNominaColumns({
    onVer:    (n) => console.log('ver', n.id),
    onCerrar: (n) => cerrar.mutate(n.id),
  })

  return (
    <Stack gap="md">
      <PageHeader
        title="Nómina"
        subtitle="Gestión de roles de pago y períodos de nómina"
        icon={<IconReportMoney size={24} />}
      />
      <NominaToolbar onEstadoChange={setFiltroEstado} />
      {lista.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconReportMoney}
          title="Sin nóminas registradas"
          description="Calcula la primera nómina seleccionando el período."
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}
    </Stack>
  )
}
