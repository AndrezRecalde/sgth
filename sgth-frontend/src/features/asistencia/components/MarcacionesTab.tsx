'use client'

import { useState } from 'react'
import { Stack, Text } from '@mantine/core'
import { IconClock, IconLock } from '@tabler/icons-react'
import { DataState, EmptyState, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useMarcaciones, type ParamsMarcaciones } from '../hooks/useMarcaciones'
import { marcacionesColumns } from './marcaciones.columns'
import { MarcacionesFiltros, type FiltrosMarcaciones, type ModoServidor } from './MarcacionesFiltros'

const FILTROS_INICIALES: FiltrosMarcaciones = {
  servidorId: null,
  elegido: null,
  cedulaEscrita: '',
  fechaInicio: null,
  fechaFin: null,
}

interface Props {
  /**
   * Solo las del usuario, aunque pueda ver las de todos. Es el modo del
   * portal: «Mis marcaciones» es lo propio también para Talento Humano.
   */
  soloPropias?: boolean
}

/**
 * Consulta de marcaciones del biométrico por servidor y rango de fechas.
 *
 * Las de cualquier servidor, quien tiene `ver-asistencia-todos`; los demás,
 * solo las propias. Es la regla de MarcacionController::index: aquí solo se
 * evita ofrecer un selector que terminaría en 403.
 */
export function MarcacionesTab({ soloPropias = false }: Props) {
  const { usuario, hasPermiso, hasRole } = useAuth()
  const [filtros, setFiltros] = useState<FiltrosMarcaciones>(FILTROS_INICIALES)
  const [consultados, setConsultados] = useState<ParamsMarcaciones | null>(null)

  const veTodos = !soloPropias && hasPermiso('ver-asistencia-todos')
  const propio = usuario?.servidor
  const cedulaPropia = propio?.puede_marcar ? (propio.cedula ?? null) : null
  const puedeBuscar = hasRole('admin-uath') || hasRole('asistente-uath') || hasRole('admin-ti')
  const modo: ModoServidor = !veTodos ? 'propio' : puedeBuscar ? 'buscar' : 'cedula'

  // Talento Humano ve también a quien ya no marca o ya no está activo: su
  // historial sigue en el biométrico (decisión del 2026-10-06).
  const cedula =
    modo === 'propio' ? cedulaPropia
    : modo === 'buscar' ? (filtros.elegido?.cedula ?? null)
    : /^\d{10}$/.test(filtros.cedulaEscrita) ? filtros.cedulaEscrita : null

  const { data: marcaciones = [], isFetching, error, refetch } = useMarcaciones(consultados)

  const cambiar = (cambio: Partial<FiltrosMarcaciones>) => setFiltros((f) => ({ ...f, ...cambio }))

  const consultar = () => {
    if (!cedula || !filtros.fechaInicio || !filtros.fechaFin) return
    setConsultados({ cedula, fecha_inicio: filtros.fechaInicio, fecha_fin: filtros.fechaFin })
  }

  if (!veTodos && !cedulaPropia) {
    return (
      <EmptyState
        icon={IconLock}
        title="Su usuario no tiene la marcación biométrica habilitada"
        description="Aquí se consultan las marcaciones propias. Si debería tenerla, solicítelo a Talento Humano."
      />
    )
  }

  const elegido = modo === 'buscar' ? filtros.elegido : null
  const historial = elegido && (!elegido.puede_marcar || !elegido.estado)

  return (
    <Stack gap="md">
      <MarcacionesFiltros
        modo={modo}
        servidorPropio={`${cedulaPropia} — ${[propio?.apellido, propio?.nombre].filter(Boolean).join(' ')}`}
        filtros={filtros}
        onCambiar={cambiar}
        onConsultar={consultar}
        puedeConsultar={!!cedula && !!filtros.fechaInicio && !!filtros.fechaFin}
        consultando={isFetching}
      />

      {historial && (
        <Text size="sm" c="dimmed">
          {elegido.estado
            ? 'Este servidor ya no tiene la marcación habilitada: se muestra su historial.'
            : 'Este servidor está inactivo: se muestra su historial.'}
        </Text>
      )}

      {!consultados ? (
        <EmptyState
          icon={IconClock}
          title={modo === 'propio' ? 'Seleccione un rango de fechas' : 'Seleccione un servidor y un rango de fechas'}
          description="Las marcaciones se consultan en el biométrico al pulsar «Consultar»."
        />
      ) : (
        <DataState
          loading={isFetching && !marcaciones.length}
          error={error}
          empty={marcaciones.length === 0}
          errorTitle="No se pudieron consultar las marcaciones"
          errorHint="No quiere decir que no haya marcaciones: el biométrico no respondió a la consulta."
          onRetry={() => void refetch()}
          skeletonRows={4}
          emptyProps={{ icon: IconClock, title: 'Sin marcaciones en el período' }}
        >
          <SgthTable records={marcaciones} columns={marcacionesColumns} fetching={isFetching} minHeight={200} />
        </DataState>
      )}
    </Stack>
  )
}
