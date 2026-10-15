'use client'

import { Button, Group, Paper, Skeleton, Stack, Text } from '@mantine/core'
import { IconArrowBackUp, IconX } from '@tabler/icons-react'
import { SgthDrawer, StatusBadge, confirmar } from '@/components/ui'
import { formatFecha, formatFechaMesHora } from '@/lib/fecha'
import { ETIQUETA_VERSION, REGLAS_VARIANTE, TONO_VERSION } from '../constants/identidad'
import { useMutacionesIdentidad, useVersionesIdentidad } from '../hooks/useIdentidad'
import type { EstadoVariante, VersionIdentidad } from '../services/tiposIdentidad'
import { VistaVersion } from './VistaVersion'

interface Props {
  variante: EstadoVariante | null
  onClose: () => void
}

/**
 * Todas las versiones de una variante (fase 2.3). Ninguna se borra: una
 * anterior se vuelve a usar con una versión nueva, y solo una programada, que
 * todavía no rige, se anula.
 */
export function HistorialVersionesDrawer({ variante, onClose }: Props) {
  const { data: versiones = [], isLoading } = useVersionesIdentidad(variante?.variante ?? null)
  const { reutilizar, anular } = useMutacionesIdentidad()
  const oscuro = variante ? REGLAS_VARIANTE[variante.variante].fondoOscuro : false

  const volver = (v: VersionIdentidad) => confirmar({
    title: 'Volver a esta versión',
    message: <>Se crea una versión nueva con este archivo, <b>vigente desde hoy</b>. Lo emitido antes no cambia.</>,
    confirmLabel: 'Volver a esta versión',
    onConfirm: () => reutilizar.mutate({ versionId: v.id }),
  })

  const pedirAnulacion = (v: VersionIdentidad) => confirmar({
    title: 'Anular la versión programada',
    message: <>La versión que iba a regir desde el <b>{formatFecha(v.vigente_desde)}</b> no llegará a usarse.</>,
    confirmLabel: 'Anular la versión',
    destructiva: true,
    onConfirm: () => anular.mutate(v.id),
  })

  return (
    <SgthDrawer opened={!!variante} onClose={onClose} title="Historial de versiones" description={variante?.etiqueta} ancho="sm">
      {isLoading ? (
        <Stack gap="sm"><Skeleton height={130} radius="md" /><Skeleton height={130} radius="md" /></Stack>
      ) : (
        <Stack gap="sm">
          {versiones.map((v) => (
            <Paper key={v.id} withBorder p="sm" radius="md">
              <Stack gap={6}>
                <VistaVersion versionId={v.id} alt={`${v.variante_etiqueta}, desde el ${formatFecha(v.vigente_desde)}`} alto={44} oscuro={oscuro} />
                <Group justify="space-between" wrap="nowrap">
                  <Text size="sm" fw={500}>Rige desde el {formatFecha(v.vigente_desde)}</Text>
                  <StatusBadge tone={TONO_VERSION[v.estado]} size="xs">{ETIQUETA_VERSION[v.estado]}</StatusBadge>
                </Group>
                <Text size="xs" c="dimmed">
                  {v.origen_etiqueta} · {formatFechaMesHora(v.subido_en)}{v.subido_por ? ` · ${v.subido_por.nombre}` : ''}
                </Text>
                {v.nota && <Text size="xs">{v.nota}</Text>}
                {v.estado === 'anulada' && (
                  <Text size="xs" c="dimmed">Anulada {formatFechaMesHora(v.anulada_en)}{v.anulada_por ? ` · ${v.anulada_por.nombre}` : ''}</Text>
                )}
                <Group gap="xs">
                  {v.estado === 'programada' && (
                    <Button size="xs" variant="light" color="red" leftSection={<IconX size={14} />} onClick={() => pedirAnulacion(v)}>
                      Anular
                    </Button>
                  )}
                  {(v.estado === 'anterior' || v.estado === 'anulada') && (
                    <Button size="xs" variant="light" leftSection={<IconArrowBackUp size={14} />} onClick={() => volver(v)}
                      loading={reutilizar.isPending && reutilizar.variables?.versionId === v.id}>
                      Volver a esta versión
                    </Button>
                  )}
                </Group>
              </Stack>
            </Paper>
          ))}
        </Stack>
      )}
    </SgthDrawer>
  )
}
