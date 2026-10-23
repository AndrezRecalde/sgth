'use client'

import { Button, Group, Paper, Stack, Text } from '@mantine/core'
import { IconFileText, IconTrash } from '@tabler/icons-react'
import { confirmar, SectionHeading, StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFechaMes, formatFechaMesHora } from '@/lib/fecha'
import { ESTADO_ENVIO } from '../constants/misExamenes'
import { useMisExamenesMutations } from '../hooks/useMisExamenes'
import type { MiOrdenExamen } from '../services/misExamenesService'

/**
 * Lo que el servidor envió contra una orden y en qué quedó: por validar (y se
 * puede retirar), validado o rechazado con su motivo.
 */
export function MisEnviosDeOrden({ orden }: { orden: MiOrdenExamen }) {
  const { retirar, verArchivo } = useMisExamenesMutations()
  const envios = orden.envios ?? []
  if (!envios.length) return null

  const nombreDe = (id: number) => orden.items?.find((i) => i.id === id)?.nombre ?? 'Examen'

  const preguntarRetiro = (id: number) => confirmar({
    title: 'Retirar el envío',
    message: 'El Dispensario ya no lo revisará. Si se equivocó de archivo, después puede enviar el correcto.',
    confirmLabel: 'Retirar',
    destructiva: true,
    onConfirm: () => retirar.mutate(id),
  })

  return (
    <Stack gap="xs">
      <SectionHeading title="Lo que usted envió" />
      {envios.map((e) => {
        const estado = ESTADO_ENVIO[e.estado]
        return (
          <Paper key={e.id} withBorder radius="md" p="xs">
            <Group justify="space-between" align="flex-start" wrap="wrap" gap="xs">
              <Stack gap={2}>
                <Group gap="xs">
                  <Text size="sm" fw={500}>{e.items.map(nombreDe).join(', ')}</Text>
                  <StatusBadge size="xs" tone={estado.tone}>{estado.label}</StatusBadge>
                </Group>
                <Text size="xs" c="dimmed">
                  Resultado del {formatFechaMes(e.fecha_resultado)} · enviado el {formatFechaMesHora(e.enviado_en)}
                </Text>
                {e.comentario && <Text size="xs" c="dimmed">«{e.comentario}»</Text>}
                {e.estado === 'rechazado' && e.motivo_rechazo && (
                  <Text size="xs" c={SEMANTIC_COLOR.danger}>Motivo: {e.motivo_rechazo}</Text>
                )}
              </Stack>
              <Group gap={4}>
                <Button size="compact-xs" variant="subtle" leftSection={<IconFileText size={14} />}
                  loading={verArchivo.isPending && verArchivo.variables === e.id}
                  onClick={() => verArchivo.mutate(e.id)}>
                  Ver archivo
                </Button>
                {e.estado === 'por_validar' && (
                  <Button size="compact-xs" variant="subtle" color={SEMANTIC_COLOR.danger}
                    leftSection={<IconTrash size={14} />} onClick={() => preguntarRetiro(e.id)}>
                    Retirar
                  </Button>
                )}
              </Group>
            </Group>
          </Paper>
        )
      })}
    </Stack>
  )
}
