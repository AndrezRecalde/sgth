'use client'

import { useState } from 'react'
import { Button, Group, Paper, Skeleton, Stack, Text } from '@mantine/core'
import { IconIdOff, IconPrinter, IconRotate } from '@tabler/icons-react'
import { EmptyState, SgthDrawer, StatusBadge } from '@/components/ui'
import { formatFecha, formatFechaMesHora } from '@/lib/fecha'
import { ESTADO_CREDENCIAL } from '../constants/credenciales'
import { useHistorialCredenciales } from '../hooks/useCredenciales'
import type { Credencial, ServidorConCredencial } from '../services/tiposCredencial'
import { DevolucionTarjetaModal } from './DevolucionTarjetaModal'

interface Props {
  servidor: ServidorConCredencial | null
  onClose: () => void
  /** Solo Comunicación recibe la tarjeta; Talento Humano y Auditoría consultan. */
  puedeGestionar: boolean
}

const quien = (persona?: { nombre: string } | null) => (persona ? ` · ${persona.nombre}` : '')

/** Una tarjeta impresa que no ha vuelto, de una credencial que ya no vale. */
const tarjetaPorRecibir = (c: Credencial) =>
  (c.estado === 'revocada' || c.estado === 'anulada') && !c.devuelta_en && (c.impresiones?.length ?? 0) > 0

/**
 * Todas las credenciales de un servidor con sus impresiones (fase 3.2): la
 * viva arriba y, debajo, las revocadas o anuladas, con la devolución de su
 * tarjeta.
 */
export function HistorialCredencialesDrawer({ servidor, onClose, puedeGestionar }: Props) {
  const { data: credenciales = [], isLoading } = useHistorialCredenciales(servidor?.id ?? null)
  const [recibiendo, setRecibiendo] = useState<Credencial | null>(null)

  return (
    <SgthDrawer opened={!!servidor} onClose={onClose} title="Historial de la credencial" description={servidor?.nombre_completo} ancho="sm">
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={120} radius="md" />
          <Skeleton height={120} radius="md" />
        </Stack>
      ) : credenciales.length === 0 ? (
        <EmptyState
          icon={IconIdOff}
          title="Sin credencial"
          description="Se crea sola cuando el servidor, con vínculo vigente, tiene su foto."
        />
      ) : (
        <Stack gap="sm">
          {credenciales.map((c) => (
            <Paper key={c.id} withBorder p="sm" radius="md">
              <Stack gap={6}>
                <Group justify="space-between" wrap="nowrap">
                  <StatusBadge tone={ESTADO_CREDENCIAL[c.estado]} size="sm">{c.estado_etiqueta}</StatusBadge>
                  <Text size="xs" c="dimmed">Creada el {formatFecha(c.creada_en)}</Text>
                </Group>

                {c.valida_hasta && <Text size="sm">Válida hasta el {formatFecha(c.valida_hasta)}</Text>}
                {c.reemplaza_a_id && (
                  <Text size="xs" c="dimmed">
                    <IconRotate size={12} style={{ verticalAlign: 'middle' }} /> Repone a una revocada
                  </Text>
                )}
                {c.revocada_en && (
                  <Text size="sm">
                    Revocada el {formatFechaMesHora(c.revocada_en)}{quien(c.revocada_por)}: {c.motivo_etiqueta?.toLowerCase()}
                    {c.detalle_revocacion ? `. ${c.detalle_revocacion}` : ''}
                  </Text>
                )}
                {c.anulada_en && <Text size="sm">Anulada el {formatFecha(c.anulada_en)}: terminó el vínculo.</Text>}
                {c.devuelta_en && (
                  <Text size="sm">
                    Tarjeta devuelta el {formatFecha(c.devuelta_en)}{quien(c.devolucion_registrada_por)}
                    {c.devolucion_nota ? `. ${c.devolucion_nota}` : ''}
                  </Text>
                )}

                <Text size="xs" fw={600} c="dimmed" mt={4}>Impresiones</Text>
                {(c.impresiones?.length ?? 0) === 0 ? (
                  <Text size="xs" c="dimmed">Nunca se imprimió.</Text>
                ) : (
                  <Stack gap={2}>
                    {c.impresiones?.map((i) => (
                      <Text key={i.id} size="xs">
                        <IconPrinter size={12} style={{ verticalAlign: 'middle' }} />{' '}
                        {formatFechaMesHora(i.impresa_en)} · {i.formato_etiqueta}{quien(i.impresa_por)}
                        {i.datos_impresos.cargo ? ` · ${i.datos_impresos.cargo}` : ''}
                      </Text>
                    ))}
                  </Stack>
                )}

                {puedeGestionar && tarjetaPorRecibir(c) && (
                  <Group justify="flex-end" mt={4}>
                    <Button size="xs" variant="light" onClick={() => setRecibiendo(c)}>Recibir la tarjeta</Button>
                  </Group>
                )}
              </Stack>
            </Paper>
          ))}
        </Stack>
      )}

      <DevolucionTarjetaModal credencial={recibiendo} nombre={servidor?.nombre_completo} onClose={() => setRecibiendo(null)} />
    </SgthDrawer>
  )
}
