'use client'

import { useState } from 'react'
import { SimpleGrid, Stack } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconChecklist, IconEye, IconHourglass, IconUsers } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable, StatCard } from '@/components/ui'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { NIVEL_DIRECCION } from '../constants/comunicados'
import { POR_PAGINA_LECTURAS, useDescargaPendientes, useLecturas, useResumenLecturas } from '../hooks/useSeguimientoComunicado'
import type { Comunicado, FiltrosLecturas } from '../services/tipos'
import { columnasLecturas } from './lecturas.columns'
import { LecturasFiltros } from './LecturasFiltros'

const RETARDO_BUSQUEDA_MS = 300

/** «62 % de 120»; sin destinatarios no hay porcentaje que dar. */
function porcentaje(parte: number, total: number): string | undefined {
  return total > 0 ? `${Math.round((parte / total) * 100)} % de ${total}` : undefined
}

/**
 * El seguimiento de una publicación (docs/diseno-noticias-comunicados.md, 6.4):
 * las cifras, la tabla de destinatarios y el Excel de quién falta. Todo se
 * cuenta sobre los destinatarios de hoy, como en el Portal.
 */
export function SeguimientoLecturas({ comunicado: c }: { comunicado: Comunicado }) {
  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosLecturas>({})
  const [busqueda, setBusqueda] = useState('')
  const [buscar] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)

  const consulta = { ...filtros, ...(buscar.length >= 2 ? { buscar } : {}) }
  const { data: cifras, isLoading: cargandoCifras } = useResumenLecturas(c.id)
  const { data, isLoading, error, refetch } = useLecturas(c.id, consulta, page)
  const descargar = useDescargaPendientes(c.id)
  const filas = data?.data ?? []

  // Si se envió a unas direcciones, el filtro ofrece solo esas; si no, todas.
  const { data: todas = [] } = useTodasUnidades({ nivel: NIVEL_DIRECCION })
  const elegidas = c.audiencia === 'segmentada' ? (c.unidades ?? []) : []
  const direcciones = (elegidas.length ? elegidas : todas)
    .map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))

  const hayFiltros = buscar !== '' || Object.values(filtros).some((v) => v !== undefined)
  const cambiar = (cambio: Partial<FiltrosLecturas>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
  }

  const total = cifras?.destinatarios ?? 0

  return (
    <Stack gap="lg">
      <SimpleGrid cols={{ base: 1, xs: 2, md: c.requiere_confirmacion ? 4 : 3 }} spacing="md">
        <StatCard label="Destinatarios" value={total} icon={IconUsers} hint="Quienes la ven hoy" loading={cargandoCifras} />
        <StatCard
          label="Leídos"
          value={cifras?.leidos ?? 0}
          icon={IconEye}
          hint={porcentaje(cifras?.leidos ?? 0, total)}
          loading={cargandoCifras}
        />
        {c.requiere_confirmacion && (
          <StatCard
            label="Confirmados"
            value={cifras?.confirmados ?? 0}
            icon={IconChecklist}
            tone="success"
            hint={porcentaje(cifras?.confirmados ?? 0, total)}
            loading={cargandoCifras}
          />
        )}
        <StatCard
          label={c.requiere_confirmacion ? 'Sin confirmar' : 'Sin leer'}
          value={cifras?.pendientes ?? 0}
          icon={IconHourglass}
          tone={cifras?.pendientes ? 'warning' : undefined}
          hint={porcentaje(cifras?.pendientes ?? 0, total)}
          loading={cargandoCifras}
        />
      </SimpleGrid>

      <LecturasFiltros
        requiereConfirmacion={c.requiere_confirmacion}
        direcciones={direcciones}
        filtros={filtros}
        busqueda={busqueda}
        onBusqueda={(texto) => { setBusqueda(texto); setPage(1) }}
        onCambiar={cambiar}
        hayPendientes={!!cifras?.pendientes}
        descargando={descargar.isPending}
        onDescargar={() => descargar.mutate(filtros.unidad_administrativa_id)}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los destinatarios"
        onRetry={refetch}
        empty={!filas.length}
        emptyProps={hayFiltros
          ? { icon: IconUsers, title: 'Nadie con estos filtros', description: 'Cambie o quite los filtros para ver a los demás destinatarios.' }
          : { icon: IconUsers, title: 'Sin destinatarios', description: 'Hoy nadie cumple con a quién va esta publicación.' }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          idAccessor="user_id"
          records={filas}
          columns={columnasLecturas(c.requiere_confirmacion)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA_LECTURAS}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </Stack>
  )
}
