'use client'

import { Group, Button, Stack, Alert, Text, Divider } from '@mantine/core'
import {
  IconCheck, IconX, IconBan, IconPlane,
  IconFileInvoice, IconDownload,
  IconFileText, IconReceipt, IconArrowBack,
} from '@tabler/icons-react'
import type { ViaticoConRelaciones } from '@/types/api'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import { resumenRevision } from '../utils/revisionComprobantes'

interface Props {
  viatico:        ViaticoConRelaciones
  estadoActual:   string
  puede:          AccionesViatico
  onAprobar:      () => void
  onEntregar:     () => void
  onComision:     () => void
  onPendiente:    () => void
  onContabilizar: () => void
  onCancelar:     () => void
  onRechazar:     () => void
  onSolicitud:    () => void
  onInforme:      () => void
  onComprobante:  () => void
  onDevolverCorreccion: () => void
  loadings: {
    aprobar:      boolean
    anticipo:     boolean
    comision:     boolean
    pendiente:    boolean
    contabilizar: boolean
    cancelar:     boolean
    rechazar:     boolean
    solicitud:    boolean
    informe:      boolean
    comprobante:  boolean
    devolverCorreccion: boolean
  }
}

/**
 * Los PDF y las acciones del viático. Cada botón aparece solo en el estado en
 * que la acción es posible y a quien puede usarla (`useAccionesViatico`).
 */
export function ViaticoAcciones({
  viatico: d,
  estadoActual,
  puede,
  onAprobar,
  onEntregar,
  onComision,
  onPendiente,
  onContabilizar,
  onCancelar,
  onRechazar,
  onSolicitud,
  onInforme,
  onComprobante,
  onDevolverCorreccion,
  loadings,
}: Props) {
  const conLiquidacion = ['pendiente_liquidacion', 'liquidado', 'contabilizado']
    .includes(estadoActual)

  // A quien aprueba o contabiliza pero viaja en este viático se le dice por
  // qué no tiene el botón, en vez de solo ocultárselo.
  const apruebaOtro =
    estadoActual === 'solicitado' && puede.rechazar(d) && !puede.aprobar(d)
  const contabilizaOtro = puede.revisarLiquidacion(d) && !puede.contabilizar(d)
  const revision = resumenRevision(d.liquidacion?.detalles_factura)

  return (
    <Stack gap="sm">
      <Group>
        <Button
          size="xs" variant="light" color="blue"
          leftSection={<IconFileText size={14} />}
          loading={loadings.solicitud}
          onClick={onSolicitud}
        >
          Solicitud PDF
        </Button>
        {conLiquidacion && (
          <Button
            size="xs" variant="light" color="orange"
            leftSection={<IconDownload size={14} />}
            loading={loadings.informe}
            onClick={onInforme}
          >
            Informe PDF
          </Button>
        )}
        {estadoActual === 'contabilizado' && (
          <Button
            size="xs" variant="light" color="gray"
            leftSection={<IconReceipt size={14} />}
            loading={loadings.comprobante}
            onClick={onComprobante}
          >
            Comprobante
          </Button>
        )}
      </Group>

      <Divider />

      <Group>
        {puede.aprobar(d) && (
          <Button size="sm" color="blue" leftSection={<IconCheck size={14} />}
            loading={loadings.aprobar} onClick={onAprobar}>
            Aprobar viático
          </Button>
        )}
        {puede.entregarAnticipo(d) && (
          <Button size="sm" color="cyan" leftSection={<IconCheck size={14} />}
            loading={loadings.anticipo} onClick={onEntregar}>
            Entregar anticipo
          </Button>
        )}
        {puede.marcarEnComision(d) && (
          <Button size="sm" variant="light" color="violet" leftSection={<IconPlane size={14} />}
            loading={loadings.comision} onClick={onComision}>
            Marcar en comisión
          </Button>
        )}
        {puede.marcarPendiente(d) && (
          <Button size="sm" color="yellow" leftSection={<IconFileInvoice size={14} />}
            loading={loadings.pendiente} onClick={onPendiente}>
            Marcar pendiente liquidación
          </Button>
        )}
        {puede.contabilizar(d) && (
          <Button size="sm" color="emerald" leftSection={<IconCheck size={14} />}
            loading={loadings.contabilizar} onClick={onContabilizar} disabled={!revision.completa}>
            Contabilizar
          </Button>
        )}
        {puede.revisarLiquidacion(d) && (
          <Button size="sm" variant="light" color="orange" leftSection={<IconArrowBack size={14} />}
            loading={loadings.devolverCorreccion} onClick={onDevolverCorreccion}>
            Devolver a corrección
          </Button>
        )}
        {puede.cancelar(d) && (
          <Button size="sm" variant="light" color="red" leftSection={<IconX size={14} />}
            loading={loadings.cancelar} onClick={onCancelar}>
            Cancelar solicitud
          </Button>
        )}
        {puede.rechazar(d) && (
          <Button size="xs" variant="subtle" color="red" leftSection={<IconBan size={12} />}
            loading={loadings.rechazar} onClick={onRechazar}>
            Rechazar
          </Button>
        )}
      </Group>

      {puede.contabilizar(d) && !revision.completa && (
        <Text size="xs" c="dimmed">
          Para contabilizar, acepte todos los comprobantes
          {revision.pendientes > 0 ? ` (faltan ${revision.pendientes} por revisar)` : ''}.
        </Text>
      )}

      {(apruebaOtro || contabilizaOtro) && (
        <Alert color="gray" variant="light">
          <Text size="xs">
            Viaja en este viático: {apruebaOtro ? 'aprobarlo' : 'contabilizarlo'} le
            corresponde a otra persona de Financiero.
          </Text>
        </Alert>
      )}

      {['cancelado', 'rechazado'].includes(estadoActual) && (
        <Alert color="red" variant="light">
          <Text size="xs">
            Este viático fue <strong>{estadoActual}</strong>.
            {d.motivo_rechazo ? ` Motivo: ${d.motivo_rechazo}` : ''}
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
