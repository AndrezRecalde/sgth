'use client'

import { Group, Button, Stack, Alert, Text, Divider } from '@mantine/core'
import {
  IconCheck, IconX, IconBan, IconPlane,
  IconRoute, IconFileInvoice, IconDownload,
  IconFileText, IconReceipt, IconArrowBack,
} from '@tabler/icons-react'
import type { ViaticoConRelaciones } from '@/types/api'
import type { AccionesViatico } from '../hooks/useAccionesViatico'

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
 * Los PDF y las acciones que tocan en el estado del viático, cada una solo
 * para quien la puede usar (ver `useAccionesViatico`).
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
  const sinAnticipo = (d.modalidad_anticipo as string) === 'sin_anticipo'
  const cancela = puede.cancelar(d)

  const botonRechazar = puede.rechazar && (
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
  )

  const botonComision = (principal: boolean) => puede.operar && (
    <Button
      size="sm"
      variant={principal ? 'filled' : 'light'}
      color="violet"
      leftSection={principal ? <IconRoute size={14} /> : <IconPlane size={14} />}
      loading={loadings.comision}
      onClick={onComision}
    >
      Marcar en comisión
    </Button>
  )

  return (
    <Stack gap="sm">
      {/* PDFs — los ve quien puede ver el viático */}
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

      <Divider />

      {/* Acciones por estado */}
      {estadoActual === 'solicitado' && (puede.aprobar || cancela) && (
        <Group>
          {puede.aprobar && (
            <Button
              size="sm"
              color="blue"
              leftSection={<IconCheck size={14} />}
              loading={loadings.aprobar}
              onClick={onAprobar}
            >
              Aprobar viático
            </Button>
          )}
          {cancela && (
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
          )}
        </Group>
      )}

      {estadoActual === 'aprobado' && (
        <Group>
          {puede.operar && !sinAnticipo && (
            <Button
              size="sm"
              color="cyan"
              leftSection={<IconCheck size={14} />}
              loading={loadings.anticipo}
              onClick={onEntregar}
            >
              Entregar anticipo
            </Button>
          )}
          {botonComision(false)}
          {botonRechazar}
        </Group>
      )}

      {estadoActual === 'con_anticipo' && (
        <Group>
          {botonComision(true)}
          {botonRechazar}
        </Group>
      )}

      {estadoActual === 'en_comision' && (
        <Group>
          {puede.operar && (
            <Button
              size="sm"
              color="yellow"
              leftSection={<IconFileInvoice size={14} />}
              loading={loadings.pendiente}
              onClick={onPendiente}
            >
              Marcar pendiente liquidación
            </Button>
          )}
          {botonRechazar}
        </Group>
      )}

      {estadoActual === 'liquidado' && (
        <Group>
          {puede.revisarLiquidacion && (
            <>
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
                size="sm"
                variant="light"
                color="orange"
                leftSection={<IconArrowBack size={14} />}
                loading={loadings.devolverCorreccion}
                onClick={onDevolverCorreccion}
              >
                Devolver a corrección
              </Button>
            </>
          )}
          {botonRechazar}
        </Group>
      )}

      {['cancelado', 'rechazado'].includes(estadoActual) && (
        <Alert color="red" variant="light">
          <Text size="xs">
            Este viático fue <strong>{estadoActual}</strong>.
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
