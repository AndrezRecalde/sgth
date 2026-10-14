'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, SegmentedControl } from '@mantine/core'
import { IconHeadset, IconPlus } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { NuevoTicketModal, type PropuestaTicket } from '@/features/helpdesk/components/NuevoTicketModal'
import { columnasMisTickets } from '@/features/helpdesk/components/tickets.columns'
import { FILTROS_MIS_TICKETS } from '@/features/helpdesk/constants/estados'
import { useTickets } from '@/features/helpdesk/hooks/useTickets'

type Vista = (typeof FILTROS_MIS_TICKETS)[number]['value']

const VACIOS: Record<Vista, string> = {
  en_curso: 'No tiene tickets en curso.',
  resueltos: 'No tiene soluciones por confirmar.',
  cerrados: 'Todavía no tiene tickets cerrados.',
}

/**
 * Los pedidos de ayuda del servidor a TIC: en curso, por confirmar y
 * cerrados. Se abre uno nuevo desde aquí o desde «Mis equipos».
 */
export function MisTicketsView() {
  const router = useRouter()
  const [vista, setVista] = useState<Vista>('en_curso')
  const [page, setPage] = useState(1)
  const [nuevo, setNuevo] = useState<PropuestaTicket | null>(null)
  const { data, isLoading, error } = useTickets({ propios: true, vista }, page)

  const pedir = <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setNuevo({})}>Pedir ayuda</Button>

  return (
    <PageShell>
      <PageHeader
        title="Mis tickets"
        description="Sus pedidos de ayuda a la Unidad de TIC: sígalos, responda y confirme la solución."
        actions={pedir}
      />
      <Toolbar>
        <SegmentedControl value={vista} data={FILTROS_MIS_TICKETS}
          onChange={(v) => { setVista(FILTROS_MIS_TICKETS.find((f) => f.value === v)?.value ?? 'en_curso'); setPage(1) }} />
      </Toolbar>
      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={{
          icon: IconHeadset,
          title: VACIOS[vista],
          description: vista === 'en_curso' ? '¿Algo no funciona? Pida ayuda a TIC y siga la respuesta aquí.' : undefined,
          action: vista === 'en_curso' ? pedir : undefined,
        }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasMisTickets}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200}
          onRowClick={({ record }) => router.push(ROUTES.PORTAL.MI_TICKET(record.id))} />
      </DataState>

      <NuevoTicketModal propuesta={nuevo} onClose={() => setNuevo(null)} onCreado={(t) => router.push(ROUTES.PORTAL.MI_TICKET(t.id))} />
    </PageShell>
  )
}
