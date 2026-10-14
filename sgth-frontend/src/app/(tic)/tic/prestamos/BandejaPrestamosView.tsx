'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group, Tabs, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconCalendarEvent, IconHandGrab, IconPresentation } from '@tabler/icons-react'
import { CountBadge, DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { plural } from '@/lib/plural'
import { PrestamoDirectoModal } from '@/features/prestamos-ti/components/PrestamoDirectoModal'
import { PrestamoModalesTic, accionesPrestamo, useAprobarConConfirmacion, type AccionPrestamo } from '@/features/prestamos-ti/components/PrestamoModalesTic'
import { columnasBandejaPrestamos } from '@/features/prestamos-ti/components/prestamos.columns'
import { useActaPrestamo, useBandejaPrestamos, useResumenPrestamos } from '@/features/prestamos-ti/hooks/usePrestamosTic'
import type { PrestamoEquipo, ResumenBandeja, VistaBandeja } from '@/features/prestamos-ti/services/tipos'

const PESTANAS: { value: VistaBandeja; label: string; tono?: 'warning' | 'danger' }[] = [
  { value: 'por_aprobar', label: 'Por aprobar', tono: 'warning' },
  { value: 'reservados', label: 'Reservados' },
  { value: 'en_curso', label: 'En curso' },
  { value: 'vencidos', label: 'Vencidos', tono: 'danger' },
  { value: 'historial', label: 'Historial' },
]

const VACIOS: Record<VistaBandeja, string> = {
  por_aprobar: 'No hay solicitudes esperando aprobación.',
  reservados: 'No hay reservas por entregar.',
  en_curso: 'No hay equipos prestados ahora.',
  vencidos: 'Ningún préstamo está vencido.',
  historial: 'Todavía no hay préstamos cerrados.',
}

/**
 * La bandeja de préstamos de TIC: lo que espera aprobación, lo que se retira
 * hoy, lo que está fuera y lo vencido. Desde aquí se aprueba, se entrega, se
 * recibe y se registra el préstamo directo del mostrador.
 */
export function BandejaPrestamosView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-prestamos')

  const [vista, setVista] = useState<VistaBandeja>('por_aprobar')
  const [page, setPage] = useState(1)
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), 300)
  const [accion, setAccion] = useState<AccionPrestamo>(null)
  const [directo, setDirecto] = useState(false)

  const { data, isLoading, error } = useBandejaPrestamos({ vista, ...(search.length >= 2 ? { search } : {}) }, page)
  const { data: resumen } = useResumenPrestamos()
  const aprobar = useAprobarConConfirmacion()
  const acta = useActaPrestamo()

  const accionesDe = (p: PrestamoEquipo) => accionesPrestamo(p, {
    puedeGestionar,
    abrir: setAccion,
    aprobar,
    acta: (x) => acta.mutate({ id: x.id, folio: x.folio }),
    verDetalle: (x) => router.push(ROUTES.TIC.PRESTAMO(x.id)),
  })
  const contador = (v: VistaBandeja) => (v !== 'historial' && resumen ? resumen[v as keyof ResumenBandeja] : 0)

  return (
    <PageShell>
      <PageHeader
        title="Préstamos"
        description={resumen && resumen.por_entregar_hoy > 0
          ? `Hoy se ${resumen.por_entregar_hoy === 1 ? 'retira' : 'retiran'} ${plural(resumen.por_entregar_hoy, 'préstamo', 'préstamos')}: están en «Reservados».`
          : 'Proyectores, parlantes y demás equipos que TIC presta: aprobar, entregar y recibir'}
        actions={(
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconCalendarEvent size={16} />} onClick={() => router.push(ROUTES.TIC.PRESTAMOS_AGENDA)}>
              Agenda
            </Button>
            {puedeGestionar && (
              <Button leftSection={<IconHandGrab size={16} />} onClick={() => setDirecto(true)}>Préstamo directo</Button>
            )}
          </Group>
        )}
      />

      <Tabs value={vista} onChange={(v) => { setVista(PESTANAS.find((p) => p.value === v)?.value ?? 'por_aprobar'); setPage(1) }}>
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
        <TextInput label="Buscar" placeholder="Folio, servidor o código del equipo" {...compacto}
          value={busqueda} onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }} />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={{ icon: IconPresentation, title: search ? 'Ningún préstamo coincide' : VACIOS[vista] }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasBandejaPrestamos(accionesDe)}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200}
          onRowClick={({ record }) => router.push(ROUTES.TIC.PRESTAMO(record.id))} />
      </DataState>

      <PrestamoModalesTic accion={accion} onClose={() => setAccion(null)} />
      <PrestamoDirectoModal opened={directo} onClose={() => setDirecto(false)} />
    </PageShell>
  )
}
