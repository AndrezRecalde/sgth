'use client'

import { useState } from 'react'
import { Alert, Select, Stack, Text } from '@mantine/core'
import { IconBan, IconFileText, IconInbox, IconInfoCircle, IconPlayerPlay } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, MotivoModal, SgthTable, StatusBadge, TableActions, Toolbar, confirmar, notificar, type TableAction,
} from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { usePrepararSolicitud, useRechazarSolicitud, useSolicitudesAccion } from '../hooks/useSolicitudesAccion'
import { solicitudesAccionService } from '../services/solicitudesAccionService'
import type { EstadoSolicitudAccion, SolicitudAccion } from '@/features/portal/services/misSolicitudesService'

const ESTADOS = [
  { value: 'pendiente', label: 'Pendientes' },
  { value: 'atendida', label: 'Atendidas' },
  { value: 'rechazada', label: 'Rechazadas' },
  { value: 'retirada', label: 'Retiradas' },
]

const TONO: Record<EstadoSolicitudAccion, SemanticTone> = {
  pendiente: 'warning',
  atendida: 'success',
  rechazada: 'danger',
  retirada: 'neutral',
}

/**
 * Las solicitudes del portal (diseño, 9.13; fase 6.3b): renuncia, licencia sin
 * remuneración y jubilación. Se atienden preparando la acción —un borrador en
 * la bandeja, con sus datos— o se rechazan con el motivo, que el servidor ve.
 * La renuncia sin respuesta en 15 días se tiene por aceptada (Reglamento a la
 * LOSEP, Art. 102): ya no se rechaza.
 */
export function SolicitudesDelPortalPanel() {
  const contained = useContainedInput('sm')
  const [estado, setEstado] = useState<EstadoSolicitudAccion | null>('pendiente')
  const { data, isLoading, error, refetch } = useSolicitudesAccion(estado)
  const preparar = usePrepararSolicitud()
  const rechazar = useRechazarSolicitud()
  const [rechazando, setRechazando] = useState<SolicitudAccion | null>(null)
  const solicitudes = data ?? []
  const vencidas = solicitudes.filter((s) => s.aceptada_por_el_plazo).length

  const verRespaldo = async (s: SolicitudAccion) => {
    try {
      const blob = await solicitudesAccionService.documento(s.id)
      if (!abrirOGuardarArchivo(blob, s.documento_nombre)) {
        notificar.aviso('El navegador bloqueó la pestaña', 'El respaldo se descargó.')
      }
    } catch (e) {
      notificar.error('No se pudo abrir el respaldo', getApiErrorMessage(e))
    }
  }

  const accionesDe = (s: SolicitudAccion): TableAction[] => [
    { label: 'Ver el respaldo', icon: <IconFileText size={14} />, onClick: () => verRespaldo(s) },
    ...(s.estado === 'pendiente'
      ? [
          {
            label: 'Preparar la acción',
            icon: <IconPlayerPlay size={14} />,
            onClick: () => confirmar({
              title: 'Preparar la acción',
              message: `Se prepara en borrador la acción que atiende ${s.folio}, con sus fechas y su referencia. `
                + 'La revisa y la corrige en la bandeja antes de suscribirla.',
              confirmLabel: 'Preparar',
              onConfirm: () => preparar.mutate(s.id),
            }),
          },
          {
            label: 'Rechazar',
            icon: <IconBan size={14} />,
            color: 'red',
            hidden: s.aceptada_por_el_plazo,
            onClick: () => setRechazando(s),
          },
        ]
      : []),
  ]

  const nombre = (s: SolicitudAccion) => [s.servidor?.apellido, s.servidor?.nombre].filter(Boolean).join(' ') || '—'

  const columnas: DataTableColumn<SolicitudAccion>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (s) => (
        <div>
          <Text size="sm" fw={500}>{nombre(s)}</Text>
          <Text size="xs" c="dimmed">{s.servidor?.cedula}</Text>
        </div>
      ),
    },
    {
      accessor: 'tipo',
      title: 'Solicitud',
      render: (s) => (
        <div>
          <Text size="sm">{s.tipo_etiqueta}{s.causal_etiqueta ? ` — ${s.causal_etiqueta}` : ''}</Text>
          <Text size="xs" c="dimmed">{s.folio} · presentada el {formatFecha(s.fecha_presentacion)}</Text>
        </div>
      ),
    },
    {
      accessor: 'fecha_desde',
      title: 'Desde',
      width: 170,
      render: (s) => (s.fecha_hasta ? `${formatFecha(s.fecha_desde)} al ${formatFecha(s.fecha_hasta)}` : formatFecha(s.fecha_desde)),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 230,
      render: (s) => (
        <div>
          <StatusBadge tone={TONO[s.estado]}>{s.estado_etiqueta}</StatusBadge>
          {s.aceptada_por_el_plazo && (
            <Text size="xs" c="dimmed" mt={2}>Se tiene por aceptada (Reglamento a la LOSEP, Art. 102)</Text>
          )}
          {s.estado === 'rechazada' && s.motivo_rechazo && <Text size="xs" c="dimmed" mt={2}>{s.motivo_rechazo}</Text>}
          {s.accion && <Text size="xs" c="dimmed" mt={2}>Acción {s.accion.codigo_registro ?? 'en borrador'}</Text>}
        </div>
      ),
    },
    { accessor: 'acciones', title: '', width: 50, render: (s) => <TableActions actions={accionesDe(s)} /> },
  ]

  return (
    <Stack gap="md">
      <Toolbar actions={<Text size="sm" c="dimmed">{solicitudes.length} solicitud(es)</Text>}>
        <Select
          label="Estado"
          placeholder="Todas"
          data={ESTADOS}
          value={estado}
          onChange={(v) => setEstado(v as EstadoSolicitudAccion | null)}
          clearable
          {...contained}
          style={{ minWidth: 220 }}
        />
      </Toolbar>

      {vencidas > 0 && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />}>
          {vencidas === 1
            ? 'Una renuncia lleva más de 15 días sin respuesta: se tiene por aceptada. Prepare la acción.'
            : `${vencidas} renuncias llevan más de 15 días sin respuesta: se tienen por aceptadas. Prepare sus acciones.`}
        </Alert>
      )}

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las solicitudes del portal"
        onRetry={refetch}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconInbox,
          title: 'Sin solicitudes',
          description: estado === 'pendiente'
            ? 'No hay solicitudes del portal esperando.'
            : 'No hay solicitudes en ese estado.',
        }}
      >
        <SgthTable records={solicitudes} columns={columnas} minHeight={160} />
      </DataState>

      <MotivoModal
        opened={rechazando !== null}
        onClose={() => setRechazando(null)}
        title={`Rechazar ${rechazando?.folio ?? 'la solicitud'}`}
        descripcion="El servidor ve el motivo en su portal."
        confirmLabel="Rechazar"
        destructiva
        cargando={rechazar.isPending}
        onConfirm={(motivo) => {
          if (!rechazando) return
          rechazar.mutate({ id: rechazando.id, motivo }, { onSuccess: () => setRechazando(null) })
        }}
      />
    </Stack>
  )
}
