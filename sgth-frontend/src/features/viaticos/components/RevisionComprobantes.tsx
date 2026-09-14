'use client'

import { useState } from 'react'
import { Alert, Group, Stack, Text } from '@mantine/core'
import { MotivoModal, StatusBadge } from '@/components/ui'
import { useAccionesViatico } from '../hooks/useAccionesViatico'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { resumenRevision } from '../utils/revisionComprobantes'
import { ComprobanteRevisionItem } from './ComprobanteRevisionItem'
import type { ComprobanteRevisado, ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
}

/*
| La revisión de los comprobantes de una liquidación presentada.
|
| Financiero acepta u observa cada uno; solo se contabiliza con todos
| aceptados, y con alguno observado se devuelve a corrección. Quien no revisa
| —el servidor, Talento Humano, Financiero en su propio viático— ve el estado
| sin poder cambiarlo.
*/
export function RevisionComprobantes({ viatico }: Props) {
  const puede = useAccionesViatico()
  const { revisarFactura } = useViaticoMutations()
  const [observando, setObservando] = useState<ComprobanteRevisado | null>(null)

  const facturas = viatico.liquidacion?.detalles_factura ?? []
  const resumen = resumenRevision(facturas)
  // Revisar es parte de contabilizar: las mismas condiciones.
  const revisa = puede.contabilizar(viatico)

  if (facturas.length === 0) return null

  const decidir = (factura: ComprobanteRevisado, decision: 'aceptada' | 'observada', observacion?: string) =>
    revisarFactura.mutate(
      { viaticoId: viatico.id, facturaId: Number(factura.id), decision, observacion },
      { onSuccess: () => setObservando(null) },
    )

  return (
    <Stack gap="xs">
      <Group justify="space-between">
        <Text size="xs" fw={600} c="dimmed">COMPROBANTES</Text>
        <Group gap={6}>
          <StatusBadge tone="success">{resumen.aceptadas} aceptado(s)</StatusBadge>
          {resumen.observadas > 0 && <StatusBadge tone="danger">{resumen.observadas} observado(s)</StatusBadge>}
          {resumen.pendientes > 0 && <StatusBadge>{resumen.pendientes} por revisar</StatusBadge>}
        </Group>
      </Group>

      {revisa && resumen.observadas > 0 && (
        <Alert color="red" variant="light" p="xs">
          <Text size="xs">Con comprobantes observados no se contabiliza: devuelva la liquidación a corrección.</Text>
        </Alert>
      )}

      {facturas.map((f) => (
        <ComprobanteRevisionItem
          key={f.id}
          factura={f}
          cargando={revisarFactura.isPending && revisarFactura.variables?.facturaId === Number(f.id)}
          onAceptar={revisa ? () => decidir(f, 'aceptada') : undefined}
          onObservar={revisa ? () => setObservando(f) : undefined}
        />
      ))}

      <MotivoModal
        opened={observando !== null}
        onClose={() => setObservando(null)}
        title="Observar comprobante"
        confirmLabel="Observar"
        cargando={revisarFactura.isPending}
        descripcion={`${observando?.nombre_proveedor ?? ''}: el servidor verá esta observación al corregir la liquidación.`}
        onConfirm={(motivo) => observando && decidir(observando, 'observada', motivo)}
      />
    </Stack>
  )
}
