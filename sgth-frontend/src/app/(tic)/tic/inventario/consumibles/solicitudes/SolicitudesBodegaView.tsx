'use client'

import { useState } from 'react'
import { Button, Select, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconFileInvoice, IconPlus } from '@tabler/icons-react'
import { DataState, MotivoModal, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DespacharSolicitudModal } from '@/features/consumibles-ti/components/DespacharSolicitudModal'
import { NuevaSolicitudModal } from '@/features/consumibles-ti/components/NuevaSolicitudModal'
import { columnasSolicitudes } from '@/features/consumibles-ti/components/solicitudes.columns'
import { ESTADO_SOLICITUD } from '@/features/consumibles-ti/constants/consumibles'
import { useSolicitudMutations, useSolicitudes } from '@/features/consumibles-ti/hooks/useSolicitudes'
import type { EstadoSolicitud, SolicitudBodega } from '@/features/consumibles-ti/services/solicitudesService'

const RETARDO_BUSQUEDA_MS = 300

const ESTADOS = (Object.keys(ESTADO_SOLICITUD) as EstadoSolicitud[]).map((e) => ({ value: e, label: ESTADO_SOLICITUD[e].etiqueta }))

/**
 * Las solicitudes de materiales informáticos a bodega (fase 5.4c). Se generan
 * aquí —mientras la mesa de ayuda no arranque— o desde un ticket
 * «Consumibles»; se imprimen para las firmas y, cuando Bodega despacha, se
 * anota lo despachado, que es la entrega.
 */
export function SolicitudesBodegaView() {
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState<EstadoSolicitud | undefined>(undefined)
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)
  const [nueva, setNueva] = useState(false)
  const [despachando, setDespachando] = useState<SolicitudBodega | null>(null)
  const [anulando, setAnulando] = useState<SolicitudBodega | null>(null)

  const filtros = { estado, ...(search.length >= 2 ? { search } : {}) }
  const { data, isLoading, error } = useSolicitudes(filtros, page)
  const { anular, pdf } = useSolicitudMutations()
  const hayFiltros = !!estado || busqueda.trim() !== ''

  const crear = gestiona
    ? <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setNueva(true)}>Nueva solicitud</Button>
    : undefined

  return (
    <PageShell>
      <PageHeader
        title="Solicitudes a bodega"
        description="Lo que TIC le pide a Bodega para un servidor: se imprime para las firmas y lo despachado es la entrega"
        actions={crear}
      />
      <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={() => { setEstado(undefined); setBusqueda(''); setPage(1) }}>Quitar los filtros</Button> : undefined}>
        <TextInput label="Buscar" placeholder="Servidor, equipo o número" {...compacto}
          value={busqueda} onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }} />
        <Select label="Estado" placeholder="Todos" data={ESTADOS} clearable {...compacto}
          value={estado ?? null} onChange={(v) => { setEstado(ESTADOS.find((e) => e.value === v)?.value); setPage(1) }} />
      </Toolbar>
      <DataState loading={isLoading} error={error} empty={!data?.data.length} page={page}
        emptyProps={hayFiltros
          ? { icon: IconFileInvoice, title: 'Ninguna solicitud con estos filtros', description: 'Quite algún filtro para ver más.' }
          : {
              icon: IconFileInvoice,
              title: 'Todavía no hay solicitudes a bodega',
              description: 'Genere una para pedir a Bodega el tóner, la tinta o el repuesto de un servidor.',
              action: crear,
            }}>
        <SgthTable
          {...PAGINACION_ES}
          records={data?.data ?? []}
          columns={columnasSolicitudes({
            onPdf: (s) => pdf.mutate({ id: s.id, codigo: s.codigo }),
            onDespachar: gestiona ? setDespachando : undefined,
            onAnular: gestiona ? setAnulando : undefined,
          })}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <NuevaSolicitudModal opened={nueva} onClose={() => setNueva(false)} onGenerada={(s) => pdf.mutate({ id: s.id, codigo: s.codigo })} />
      <DespacharSolicitudModal solicitud={despachando} onClose={() => setDespachando(null)} />
      <MotivoModal
        opened={!!anulando}
        onClose={() => setAnulando(null)}
        title="Anular solicitud"
        descripcion={anulando && <>La solicitud <b>{anulando.codigo}</b> queda anulada: ya no se despacha.</>}
        confirmLabel="Anular"
        destructiva
        placeholder="Ej.: Se pidió el tóner equivocado"
        cargando={anular.isPending}
        onConfirm={(motivo) => anulando && anular.mutate({ id: anulando.id, motivo }, { onSuccess: () => setAnulando(null) })}
      />
    </PageShell>
  )
}
