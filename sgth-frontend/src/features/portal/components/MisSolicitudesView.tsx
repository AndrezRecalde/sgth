'use client'

import { Button, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowBackUp, IconFileText, IconInbox, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, PageHeader, PageShell, SgthTable, StatusBadge, TableActions, confirmar, notificar,
  type TableAction,
} from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useMisSolicitudes, useRetirarSolicitud } from '../hooks/useMisSolicitudes'
import { misSolicitudesService, type EstadoSolicitudAccion, type SolicitudAccion } from '../services/misSolicitudesService'
import { NuevaSolicitudModal } from './NuevaSolicitudModal'

const TONO: Record<EstadoSolicitudAccion, SemanticTone> = {
  pendiente: 'warning',
  atendida: 'success',
  rechazada: 'danger',
  retirada: 'neutral',
}

/**
 * Sus solicitudes de renuncia, licencia sin remuneración y jubilación (diseño,
 * 9.13; fase 6.3b): en qué quedó cada una, el motivo si se rechazó, y la
 * acción que la atendió cuando ya se le notificó.
 */
export function MisSolicitudesView() {
  const { data, isLoading, error } = useMisSolicitudes()
  const retirar = useRetirarSolicitud()
  const [nuevaOpened, { open: abrirNueva, close: cerrarNueva }] = useDisclosure(false)
  const solicitudes = data ?? []

  const verRespaldo = async (s: SolicitudAccion) => {
    try {
      const blob = await misSolicitudesService.documento(s.id)
      if (!abrirOGuardarArchivo(blob, s.documento_nombre)) {
        notificar.aviso('El navegador bloqueó la pestaña', 'El respaldo se descargó.')
      }
    } catch (e) {
      notificar.error('No se pudo abrir el respaldo', getApiErrorMessage(e))
    }
  }

  const accionesDe = (s: SolicitudAccion): TableAction[] => [
    { label: 'Ver el respaldo', icon: <IconFileText size={14} />, onClick: () => verRespaldo(s) },
    ...(s.puede_retirar
      ? [{
          label: 'Retirar',
          icon: <IconArrowBackUp size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Retirar la solicitud',
            message: `Se retira la solicitud ${s.folio}. Talento Humano ya no la atenderá.`,
            confirmLabel: 'Retirar',
            destructiva: true,
            onConfirm: () => retirar.mutate(s.id),
          }),
        }]
      : []),
  ]

  const columnas: DataTableColumn<SolicitudAccion>[] = [
    {
      accessor: 'tipo',
      title: 'Solicitud',
      render: (s) => (
        <div>
          <Text size="sm" fw={500}>{s.tipo_etiqueta}{s.causal_etiqueta ? ` — ${s.causal_etiqueta}` : ''}</Text>
          <Text size="xs" c="dimmed">{s.folio} · presentada el {formatFecha(s.fecha_presentacion)}</Text>
        </div>
      ),
    },
    {
      accessor: 'fecha_desde',
      title: 'Desde',
      width: 180,
      render: (s) => (s.fecha_hasta
        ? `${formatFecha(s.fecha_desde)} al ${formatFecha(s.fecha_hasta)}`
        : formatFecha(s.fecha_desde)),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 240,
      render: (s) => (
        <div>
          <StatusBadge tone={TONO[s.estado]}>{s.estado_etiqueta}</StatusBadge>
          {s.estado === 'rechazada' && s.motivo_rechazo && (
            <Text size="xs" c="dimmed" mt={2}>{s.motivo_rechazo}</Text>
          )}
          {s.aceptada_por_el_plazo && (
            <Text size="xs" c="dimmed" mt={2}>Se tiene por aceptada: pasaron 15 días sin respuesta.</Text>
          )}
          {s.accion?.codigo_registro && (
            <Text size="xs" c="dimmed" mt={2}>Acción {s.accion.codigo_registro}</Text>
          )}
        </div>
      ),
    },
    { accessor: 'acciones', title: '', width: 50, render: (s) => <TableActions actions={accionesDe(s)} /> },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Renuncia, licencia y jubilación"
        description="Sus solicitudes a Talento Humano y en qué quedó cada una"
        actions={
          <Button variant="light" leftSection={<IconPlus size={16} />} onClick={abrirNueva}>
            Nueva solicitud
          </Button>
        }
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconInbox,
          title: 'No ha presentado solicitudes',
          description: 'Presente una renuncia, una licencia sin remuneración o su jubilación con «Nueva solicitud».',
        }}
      >
        <SgthTable records={solicitudes} columns={columnas} minHeight={160} />
      </DataState>

      <NuevaSolicitudModal opened={nuevaOpened} onClose={cerrarNueva} />
    </PageShell>
  )
}
