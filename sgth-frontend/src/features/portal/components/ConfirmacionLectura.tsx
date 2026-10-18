'use client'

import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconCheck, IconCircleCheck, IconSignature } from '@tabler/icons-react'
import { confirmar } from '@/components/ui'
import { formatFechaMesHora } from '@/lib/fecha'
import { useConfirmarLectura } from '@/features/comunicados/hooks/useMisComunicados'
import type { ComunicadoPortal } from '@/features/comunicados/services/tipos'

/**
 * La confirmación de lectura al final de un comunicado que la pide
 * (docs/diseno-noticias-comunicados.md, 3.4). Queda con fecha y hora, a su
 * nombre, y no se deshace; por eso se pregunta antes.
 */
export function ConfirmacionLectura({ c }: { c: ComunicadoPortal }) {
  const confirmarLectura = useConfirmarLectura(c.id)

  if (!c.requiere_confirmacion) return null

  if (c.confirmado_at) {
    return (
      <Alert variant="light" color="emerald" icon={<IconCircleCheck size={18} />} title={`Confirmó la lectura el ${formatFechaMesHora(c.confirmado_at)}`}>
        La confirmación queda registrada y no se puede deshacer.
      </Alert>
    )
  }

  if (c.vencido) {
    return (
      <Text size="sm" c="dimmed">
        Este comunicado venció sin que confirmara su lectura; la confirmación ya está cerrada.
      </Text>
    )
  }

  const preguntar = () => confirmar({
    title: 'Confirmar la lectura',
    message: (
      <Stack gap="xs">
        <Text size="sm">¿Confirma que leyó <b>{c.titulo}</b>?</Text>
        <Text size="sm" c="dimmed">Quedará registrado a su nombre, con la fecha y la hora, y no se puede deshacer.</Text>
      </Stack>
    ),
    confirmLabel: 'Sí, confirmo',
    onConfirm: () => confirmarLectura.mutate(),
  })

  return (
    <Alert variant="light" color="amber" icon={<IconSignature size={18} />} title="Confirmación de lectura">
      <Group justify="space-between" align="center" gap="md">
        <Text size="sm" style={{ flex: '1 1 260px' }}>
          Talento Humano le pide confirmar que leyó este comunicado.
        </Text>
        {/* `light`: abre la pregunta; el envío es el botón del diálogo (regla 06). */}
        <Button variant="light" leftSection={<IconCheck size={16} />} onClick={preguntar} loading={confirmarLectura.isPending}>
          Confirmo que he leído este comunicado
        </Button>
      </Group>
    </Alert>
  )
}
