'use client'

import { useState } from 'react'
import { Select } from '@mantine/core'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import {
  useSolicitudesCertificacion,
  useIniciarProceso,
  useConfirmarIncorporacion,
} from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { usePdfFemo } from '@/features/dispensario/hooks/usePdfFemo'
import { getSolicitudesSsoColumns } from '@/features/dispensario/components/solicitudes-certificacion.columns'
import {
  DataState, PageHeader, PageShell, PAGINACION_ES, SgthTable, StatusBadge,
  Toolbar,
} from '@/components/ui'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

export function SsoView() {
  const router = useRouter()
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()

  const [page, setPage] = useState(1)
  const [filtroEstado, setFiltroEstado] = useState<string>('pendiente')

  const { data, isLoading, error } = useSolicitudesCertificacion({
    page,
    per_page: POR_PAGINA,
    estado: filtroEstado || undefined,
  })

  const solicitudes = data?.data ?? []
  const iniciar = useIniciarProceso()
  const incorporar = useConfirmarIncorporacion()
  const { descargarFemo, loading: descargando } = usePdfFemo()

  // El contador de la cabecera se sacaba de las filas cargadas, que antes eran
  // hasta veinte y ahora son quince: diría «15 pendientes» habiendo cuarenta.
  // El total lo manda el servidor, y con el filtro en «pendiente» es
  // exactamente lo que hay que contar.
  const pendientes = filtroEstado === 'pendiente' ? (data?.total ?? 0) : 0

  // Cambiar el filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarEstado = (valor: string) => {
    setFiltroEstado(valor)
    setPage(1)
  }

  const columns = getSolicitudesSsoColumns({
    descargando,
    puedeConfirmarIncorporacion: hasPermiso('gestionar-onboarding'),
    onIniciar: (id) =>
      iniciar.mutate(id, {
        onSuccess: () => router.push(`/salud/sso/femo/nueva/${id}`),
      }),
    onContinuar: (id) => router.push(`/salud/sso/femo/nueva/${id}`),
    onDescargarFemo: (fichaId, nombre) => descargarFemo(fichaId, nombre),
    onConfirmarIncorporacion: (id) => incorporar.mutate(id),
  })

  return (
    <PageShell>
      <PageHeader
        title="Salud Ocupacional"
        description="Solo se pueden crear fichas FEMO a partir de una solicitud de Talento Humano"
        actions={
          pendientes > 0 ? (
            <StatusBadge tone="warning" size="md">
              {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
            </StatusBadge>
          ) : undefined
        }
      />

      <Toolbar>
        <Select
          label="Estado"
          placeholder="Todas"
          data={[
            { value: '',           label: 'Todas'       },
            { value: 'pendiente',  label: 'Pendientes'  },
            { value: 'en_proceso', label: 'En proceso'  },
            { value: 'completada', label: 'Completadas' },
            { value: 'cancelada',  label: 'Canceladas'  },
          ]}
          style={{ minWidth: 200 }}
          {...contained}
          value={filtroEstado}
          onChange={(v) => cambiarEstado(v ?? '')}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: 'Sin solicitudes',
          description: filtroEstado === 'pendiente'
            ? 'No hay solicitudes de certificación pendientes de Talento Humano.'
            : 'No hay solicitudes en este estado.',
        }}
      >
        <SgthTable
          // Solo `paginationText`: el objeto entero no compila, porque
          // `recordsPerPageLabel` exige `recordsPerPageOptions` y
          // `onRecordsPerPageChange`.
          paginationText={PAGINACION_ES.paginationText}
          records={solicitudes}
          columns={columns}
          totalRecords={data?.total ?? solicitudes.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
