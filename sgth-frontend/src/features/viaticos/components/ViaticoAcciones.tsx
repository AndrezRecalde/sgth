'use client'

import { Group, Button, Stack, Alert, Text } from '@mantine/core'
import {
  IconCheck, IconX, IconBan, IconPlane,
  IconRoute, IconFileInvoice, IconDownload,
  IconFileText, IconReceipt,
} from '@tabler/icons-react'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico:      ViaticoConRelaciones
  estadoActual: string
  onAprobar:    () => void
  onEntregar:   () => void
  onComision:   () => void
  onPendiente:  () => void
  onContabilizar: () => void
  onCancelar:   () => void
  onRechazar:   () => void
  onSolicitud:  () => void
  onInforme:    () => void
  onComprobante: () => void
  loadings: {
    aprobar:       boolean
    anticipo:      boolean
    comision:      boolean
    pendiente:     boolean
    contabilizar:  boolean
    cancelar:      boolean
    rechazar:      boolean
    solicitud:     boolean
    informe:       boolean
    comprobante:   boolean
  }
}

export function ViaticoAcciones({
  viatico: d,
  estadoActual,
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
  loadings,
}: Props) {
  return (
    <Stack gap="sm">
      {/* PDF Solicitud siempre visible */}
      <Group>
        <Button
          size="xs"
          variant="light"
          color="blue"
          leftSection={<IconFileText size={14} />}
          loading={loadings.solicitud}
          onClick={onSolicitud}
        >
          Solicitud PDF
        </Button>

        {['pendiente_liquidacion', 'liquidado',
          'contabilizado'].includes(estadoActual) && (
          <Button
            size="xs"
            variant="light"
            color="orange"
            leftSection={<IconDownload size={14} />}
            loading={loadings.informe}
            onClick={onInforme}
          >
            Informe PDF
          </Button>
        )}

        {estadoActual === 'contabilizado' && (
          <Button
            size="xs"
            variant="light"
            color="gray"
            leftSection={<IconReceipt size={14} />}
            loading={loadings.comprobante}
            onClick={onComprobante}
          >
            Comprobante
          </Button>
        )}
      </Group>

      {/* Acciones por estado */}
      {estadoActual === 'solicitado' && (
        <Group>
          <Button
            size="sm"
            color="blue"
            leftSection={<IconCheck size={14} />}
            loading={loadings.aprobar}
            onClick={onAprobar}
          >
            Aprobar viático
          </Button>
          <Button
            size="sm"
            variant="light"
            color="red"
            leftSection={<IconX size={14} />}
            loading={loadings.cancelar}
            onClick={onCancelar}
          >
            Cancelar solicitud
          </Button>
        </Group>
      )}

      {estadoActual === 'aprobado' && (
        <Group>
          <Button
            size="sm"
            color="cyan"
            leftSection={<IconCheck size={14} />}
            loading={loadings.anticipo}
            onClick={onEntregar}
          >
            Entregar anticipo
          </Button>
          <Button
            size="sm"
            variant="light"
            color="violet"
            leftSection={<IconPlane size={14} />}
            loading={loadings.comision}
            onClick={onComision}
          >
            Marcar en comisión
          </Button>
          <Button
            size="xs"
            variant="subtle"
            color="red"
            leftSection={<IconBan size={12} />}
            loading={loadings.rechazar}
            onClick={onRechazar}
          >
            Rechazar
          </Button>
        </Group>
      )}

      {estadoActual === 'con_anticipo' && (
        <Group>
          <Button
            size="sm"
            color="violet"
            leftSection={<IconRoute size={14} />}
            loading={loadings.comision}
            onClick={onComision}
          >
            Marcar en comisión
          </Button>
          <Button
            size="xs"
            variant="subtle"
            color="red"
            leftSection={<IconBan size={12} />}
            loading={loadings.rechazar}
            onClick={onRechazar}
          >
            Rechazar
          </Button>
        </Group>
      )}

      {estadoActual === 'en_comision' && (
        <Group>
          <Button
            size="sm"
            color="yellow"
            leftSection={<IconFileInvoice size={14} />}
            loading={loadings.pendiente}
            onClick={onPendiente}
          >
            Marcar pendiente liquidación
          </Button>
          <Button
            size="xs"
            variant="subtle"
            color="red"
            leftSection={<IconBan size={12} />}
            loading={loadings.rechazar}
            onClick={onRechazar}
          >
            Rechazar
          </Button>
        </Group>
      )}

      {estadoActual === 'liquidado' && (
        <Group>
          <Button
            size="sm"
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={loadings.contabilizar}
            onClick={onContabilizar}
          >
            Contabilizar
          </Button>
          <Button
            size="xs"
            variant="subtle"
            color="red"
            leftSection={<IconBan size={12} />}
            loading={loadings.rechazar}
            onClick={onRechazar}
          >
            Rechazar
          </Button>
        </Group>
      )}

      {['cancelado', 'rechazado'].includes(estadoActual) && (
        <Alert color="red" variant="light">
          <Text size="xs">
            Este viático fue{' '}
            <strong>{estadoActual}</strong>.
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
