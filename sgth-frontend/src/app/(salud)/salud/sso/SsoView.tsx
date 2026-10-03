'use client'

import { useState } from 'react'
import { Select, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useSolicitudesCertificacion,
  useIniciarProceso,
} from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { usePdfFemo } from '@/features/dispensario/hooks/usePdfFemo'
import { getSolicitudesSsoColumns } from '@/features/dispensario/components/solicitudes-certificacion.columns'
import {
  ESTADO_SOLICITUD_FILTRO_OPTIONS, TIPO_EVENTO_OPTIONS,
} from '@/features/dispensario/services/solicitudCertificacionService'
import {
  DataState, PageHeader, PageShell, PAGINACION_ES, SgthTable, StatusBadge,
  Toolbar,
} from '@/components/ui'
import { ROUTES } from '@/config/routes'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

/**
 * La bandeja del médico ocupacional: las solicitudes de evaluación que manda
 * Talento Humano. No es un tablero —el menú la llamaba «Dashboard»—, es la
 * lista de trabajo, y se ordena por lo que vence primero.
 */
export function SsoView() {
  const router    = useRouter()
  const contained = useContainedInput('sm')

  const [page, setPage]                 = useState(1)
  // Arranca con lo pendiente Y lo que ya empezó: con solo «pendiente» se
  // escondían las evaluaciones en curso, que son las que hay que continuar.
  const [filtroEstado, setFiltroEstado] = useState<string>('activas')
  const [tipoEvento, setTipoEvento]     = useState<string | null>(null)
  const [buscar, setBuscar]             = useState('')
  const [buscarDebounced]               = useDebouncedValue(buscar.trim(), 300)

  const { data, isLoading, error } = useSolicitudesCertificacion(
    {
      page,
      per_page:    POR_PAGINA,
      estado:      filtroEstado || undefined,
      tipo_evento: tipoEvento ?? undefined,
      buscar:      buscarDebounced || undefined,
      orden:       'fecha_limite',
    },
    // Talento Humano añade solicitudes desde otra pantalla y aquí tienen que
    // aparecer solas.
    { enVivo: true },
  )

  const solicitudes = data?.data ?? []
  const iniciar = useIniciarProceso()
  const { descargarFemo, loading: descargando } = usePdfFemo()

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado filtrado, casi siempre vacía.
  const filtrar = <T,>(set: (v: T) => void) => (v: T) => { set(v); setPage(1) }

  // «Confirmar incorporación» no se ofrece aquí: es de Talento Humano
  // (`gestionar-onboarding`), que no trabaja en esta pantalla.
  const columns = getSolicitudesSsoColumns({
    descargando,
    puedeConfirmarIncorporacion: false,
    onIniciar: (id) => {
      // Un doble clic mandaba dos PATCH: el segundo daba 422 y un aviso de
      // error aunque el inicio sí había funcionado.
      if (iniciar.isPending) return
      iniciar.mutate(id, {
        onSuccess: () => router.push(ROUTES.SALUD.FEMO_NUEVA(id)),
      })
    },
    onContinuar: (id) => router.push(ROUTES.SALUD.FEMO_NUEVA(id)),
    onDescargarFemo: (fichaId, nombre) => descargarFemo(fichaId, nombre),
    onConfirmarIncorporacion: () => {},
  })

  const total = data?.total ?? 0
  const hayFiltros = !!(tipoEvento || buscarDebounced)

  return (
    <PageShell>
      <PageHeader
        title="Solicitudes de evaluación médica"
        description="Las evaluaciones ocupacionales que pide Talento Humano. Toda ficha FEMO nace de una de ellas."
        actions={
          filtroEstado === 'activas' && total > 0 ? (
            <StatusBadge tone="warning" size="md">
              {total} por atender
            </StatusBadge>
          ) : undefined
        }
      />

      <Toolbar>
        <TextInput
          label="Buscar"
          placeholder="Cédula o nombre"
          {...contained}
          value={buscar}
          onChange={(e) => filtrar(setBuscar)(e.currentTarget.value)}
        />
        <Select
          label="Estado"
          placeholder="Todas"
          data={ESTADO_SOLICITUD_FILTRO_OPTIONS}
          {...contained}
          value={filtroEstado}
          onChange={(v) => filtrar(setFiltroEstado)(v ?? '')}
        />
        <Select
          label="Tipo de evaluación"
          placeholder="Todos"
          data={TIPO_EVENTO_OPTIONS}
          clearable
          {...contained}
          value={tipoEvento}
          onChange={filtrar(setTipoEvento)}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: 'Sin solicitudes',
          description: hayFiltros
            ? 'Ninguna solicitud cumple los filtros elegidos.'
            : filtroEstado === 'activas'
              ? 'No hay evaluaciones pendientes ni en curso.'
              : 'No hay solicitudes en este estado.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
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
