'use client'

import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconCheck, IconFileDownload } from '@tabler/icons-react'

interface Props {
  /** «Permiso registrado correctamente». */
  titulo:     string
  folio?:     string | null
  /** La pregunta sobre el PDF, que cambia según qué se registró. */
  pregunta:   string
  exportando: boolean
  onExportar: () => void
  onCerrar:   () => void
}

/**
 * El último paso del registro de un permiso o de unas vacaciones: el folio
 * asignado y la opción de bajar el PDF. Los dos modales lo tenían copiado.
 */
export function RegistroConfirmado({
  titulo, folio, pregunta, exportando, onExportar, onCerrar,
}: Props) {
  return (
    <Stack gap="md" align="center">
      <Alert icon={<IconCheck size={20} />} color="emerald" variant="light" w="100%">
        <Text fw={600}>{titulo}</Text>
        <Text size="sm" mt={4}>
          Folio: <strong>{folio ?? '—'}</strong>
        </Text>
      </Alert>

      <Stack gap="xs" w="100%">
        <Text size="sm" c="dimmed" ta="center">
          {pregunta}
        </Text>
        <Group justify="center" mt="xs">
          <Button
            variant="light"
            color="blue"
            leftSection={<IconFileDownload size={16} />}
            loading={exportando}
            onClick={onExportar}
          >
            Exportar PDF
          </Button>
          <Button variant="default" onClick={onCerrar}>
            Cerrar
          </Button>
        </Group>
      </Stack>
    </Stack>
  )
}
