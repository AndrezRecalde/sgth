'use client'

import { useState } from 'react'

import { Tabs } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { NominaToolbar } from '@/features/nomina/components/NominaToolbar'
import { getNominaColumns } from '@/features/nomina/components/nomina.columns'
import { NominaDetalleModal } from '@/features/nomina/components/NominaDetalleModal'
import { NovedadesNominaPanel } from '@/features/nomina/components/NovedadesNominaPanel'
import { useNominas } from '@/features/nomina/hooks/useNominas'
import { useNominaMutations } from '@/features/nomina/hooks/useNominaMutations'
import { IconListDetails, IconReportMoney } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import type { Nomina } from '@/types/api'
import { EmptyState, PageHeader, PageShell, SgthTable , confirmar } from '@/components/ui'

/**
 * Nómina: las novedades del mes, que Financiero aplica en su sistema (fase
 * 7.3a), y el cálculo de la nómina del SGTH, que el backend sirve solo a
 * admin-uath.
 */
export function NominaView() {
  const { hasPermiso, hasRole } = useAuth()
  const veNovedades = hasPermiso('ver-novedades-nomina')
  const veNominas = hasRole('admin-uath')

  return (
    <PageShell>
      <PageHeader
        title="Nómina"
        description="Las novedades del mes para el rol de pagos y los períodos de nómina"
      />
      <Tabs defaultValue={veNovedades ? 'novedades' : 'nominas'} keepMounted={false}>
        <Tabs.List mb="md">
          {veNovedades && (
            <Tabs.Tab value="novedades" leftSection={<IconListDetails size={16} />}>
              Novedades del mes
            </Tabs.Tab>
          )}
          {veNominas && (
            <Tabs.Tab value="nominas" leftSection={<IconReportMoney size={16} />}>
              Nóminas
            </Tabs.Tab>
          )}
        </Tabs.List>

        {veNovedades && (
          <Tabs.Panel value="novedades">
            <NovedadesNominaPanel />
          </Tabs.Panel>
        )}
        {veNominas && (
          <Tabs.Panel value="nominas">
            <NominasPanel />
          </Tabs.Panel>
        )}
      </Tabs>
    </PageShell>
  )
}

function NominasPanel() {
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
    <>
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
    </>
  )
}
