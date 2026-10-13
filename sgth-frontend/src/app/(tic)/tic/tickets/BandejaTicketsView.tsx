'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Select, Tabs, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconHeadset, IconPlus } from '@tabler/icons-react'
import { CountBadge, DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { RegistrarTicketModal } from '@/features/helpdesk/components/RegistrarTicketModal'
import { columnasBandejaTickets } from '@/features/helpdesk/components/ticketsTic.columns'
import { useCatalogosHelpdesk, useTickets } from '@/features/helpdesk/hooks/useTickets'
import { useResumenTickets } from '@/features/helpdesk/hooks/useTicketsTic'
import type { FiltrosTickets, PrioridadTicket, ResumenTickets, VistaBandejaTickets } from '@/features/helpdesk/services/tipos'

const PESTANAS: { value: VistaBandejaTickets; label: string; tono?: 'warning' | 'danger' }[] = [
  { value: 'sin_asignar', label: 'Sin asignar', tono: 'warning' },
  { value: 'mios', label: 'Mis tickets' },
  { value: 'en_curso', label: 'En curso' },
  { value: 'vencidos', label: 'Vencidos', tono: 'danger' },
  { value: 'resueltos', label: 'Por confirmar' },
  { value: 'cerrados', label: 'Cerrados' },
]

/**
 * La bandeja de la mesa de ayuda: lo que nadie tiene, lo propio, todo lo que
 * sigue en curso, lo vencido y lo resuelto que espera al usuario. El técnico
 * ve su cola y lo sin asignar; el jefe, todo.
 */
export function BandejaTicketsView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { data: resumen } = useResumenTickets()
  const [vista, setVista] = useState<VistaBandejaTickets>('sin_asignar')
  const [filtros, setFiltros] = useState<Omit<FiltrosTickets, 'vista' | 'search'>>({})
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), 300)
  const [page, setPage] = useState(1)
  const [registrar, setRegistrar] = useState(false)

  const { data, isLoading, error } = useTickets({ ...filtros, vista, ...(search.length >= 2 ? { search } : {}) }, page)
  const cambiar = (c: Partial<FiltrosTickets>) => { setFiltros((f) => ({ ...f, ...c })); setPage(1) }
  const contador = (v: VistaBandejaTickets) => (v !== 'cerrados' && resumen ? resumen[v as keyof ResumenTickets] : 0)
  const opciones = (lista: { id: number; nombre: string }[]) => lista.map((x) => ({ value: String(x.id), label: x.nombre }))

  return (
    <PageShell>
      <PageHeader
        title="Mesa de ayuda"
        description="Los pedidos de ayuda de los servidores: repartir, atender y resolver dentro del SLA"
        actions={hasPermiso('gestionar-tickets') ? (
          <Button leftSection={<IconPlus size={16} />} onClick={() => setRegistrar(true)}>Registrar ticket</Button>
        ) : undefined}
      />
      <Tabs value={vista} onChange={(v) => { setVista(PESTANAS.find((p) => p.value === v)?.value ?? 'sin_asignar'); setPage(1) }}>
        <Tabs.List>
          {PESTANAS.map((p) => (
            <Tabs.Tab key={p.value} value={p.value}
              rightSection={contador(p.value) > 0 ? <CountBadge tone={p.tono ?? 'neutral'} destacado={p.tono !== undefined}>{contador(p.value)}</CountBadge> : undefined}>
              {p.label}
            </Tabs.Tab>
          ))}
        </Tabs.List>
      </Tabs>
      <Toolbar>
        <TextInput label="Buscar" placeholder="Folio, asunto o servidor" {...compacto}
          value={busqueda} onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }} />
        <Select label="Área de TIC" placeholder="Todas" clearable data={opciones(catalogos?.subunidades ?? [])} {...compacto}
          value={filtros.unidad_tic_id ? String(filtros.unidad_tic_id) : null} onChange={(v) => cambiar({ unidad_tic_id: v ? Number(v) : undefined })} />
        <Select label="Categoría" placeholder="Todas" clearable data={opciones(catalogos?.categorias ?? [])} {...compacto}
          value={filtros.categoria_id ? String(filtros.categoria_id) : null} onChange={(v) => cambiar({ categoria_id: v ? Number(v) : undefined })} />
        <Select label="Prioridad" placeholder="Todas" clearable {...compacto}
          data={(catalogos?.prioridades ?? []).map((p) => ({ value: p.valor, label: p.etiqueta }))}
          value={filtros.prioridad ?? null}
          onChange={(v) => cambiar({ prioridad: catalogos?.prioridades.find((p) => p.valor === v)?.valor as PrioridadTicket | undefined })} />
        {hasPermiso('ver-tickets-todos') && (
          <Select label="Técnico" placeholder="Todos" clearable searchable data={opciones(catalogos?.tecnicos ?? [])} {...compacto}
            value={filtros.tecnico_id ? String(filtros.tecnico_id) : null} onChange={(v) => cambiar({ tecnico_id: v ? Number(v) : undefined })} />
        )}
      </Toolbar>
      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={{ icon: IconHeadset, title: 'No hay tickets aquí', description: 'Pruebe otra pestaña o quite los filtros.' }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasBandejaTickets}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200}
          onRowClick={({ record }) => router.push(ROUTES.TIC.TICKET(record.id))} />
      </DataState>

      <RegistrarTicketModal opened={registrar} onClose={() => setRegistrar(false)} onCreado={(t) => router.push(ROUTES.TIC.TICKET(t.id))} />
    </PageShell>
  )
}
