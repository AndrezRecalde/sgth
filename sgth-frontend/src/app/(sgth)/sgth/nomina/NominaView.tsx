'use client'

import { useState } from 'react'

import { useDisclosure } from '@mantine/hooks'
import { NominaToolbar } from '@/features/nomina/components/NominaToolbar'
import { getNominaColumns } from '@/features/nomina/components/nomina.columns'
import { NominaDetalleModal } from '@/features/nomina/components/NominaDetalleModal'
import { useNominas } from '@/features/nomina/hooks/useNominas'
import { useNominaMutations } from '@/features/nomina/hooks/useNominaMutations'
import { IconReportMoney } from '@tabler/icons-react'
import type { Nomina } from '@/types/api'
import { EmptyState, PageHeader, PageShell, SgthTable , confirmar } from '@/components/ui'

export function NominaView() {
  const [filtroEstado, setFiltroEstado] =
    useState<string | null>(null)
  const [nominaSel, setNominaSel] =
    useState<Nomina | null>(null)

  const [modalAbierto, { open: abrirModal, close: cerrarModal }] =
    useDisclosure(false)

  const { data: nominas = [], isLoading } = useNominas()
  const { cerrar } = useNominaMutations()

  const lista = (nominas as Nomina[]).filter(n =>
    filtroEstado ? n.estado === filtroEstado : true
  )

  const handleVer = (n: Nomina) => {
    setNominaSel(n)
    abrirModal()
  }

  const handleCerrar = (n: Nomina) => confirmar({
    title:   'Cerrar nómina',
    message: (
      <>
        Se cerrará la nómina del período <b>{n.periodo}</b>. No se puede
        deshacer.
      </>
    ),
    destructiva: true,
    confirmLabel: 'Cerrar nómina',
    onConfirm: () => cerrar.mutate(n.id),
  })

  const columns = getNominaColumns({
    onVer:    handleVer,
    onCerrar: handleCerrar,
  })

  return (
    <PageShell>
      <PageHeader
        title="Nómina"
        description="Gestión de roles de pago y períodos de nómina"
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

      <NominaDetalleModal
        opened={modalAbierto}
        onClose={cerrarModal}
        nomina={nominaSel}
      />
    </PageShell>
  )
}
