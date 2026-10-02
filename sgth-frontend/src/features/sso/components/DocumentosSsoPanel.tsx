'use client'

import { confirmar, DataState } from '@/components/ui'
import { Box, Stack, Group, Text, ActionIcon, Alert } from '@mantine/core'
import { IconDownload, IconTrash, IconAlertCircle } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { useDocumentosSso, useDocumentoSsoMutations } from '../hooks/useDocumentosSso'
import { formatFecha } from '@/lib/fecha'
import { SubirDocumentoSsoForm } from './SubirDocumentoSsoForm'
import type { TipoDocumentableSso, DocumentoSso } from '../services/documentoSsoService'

function formatTamano(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

interface Props {
  tipo: TipoDocumentableSso
  documentableId: number | null
}

/** Panel de adjuntos genérico (Fase 9): lista + sube evidencias/actas para un registro SSO. */
export function DocumentosSsoPanel({ tipo, documentableId }: Props) {
  const { data: documentos = [], isLoading, error, refetch } = useDocumentosSso(tipo, documentableId)
  const { subir, eliminar, descargar } = useDocumentoSsoMutations(tipo, documentableId)

  // Descargar la evidencia es lectura; adjuntarla y borrarla, no.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  if (!documentableId) {
    return (
      <Alert icon={<IconAlertCircle size={16} />} color="ocean" variant="light">
        Guarde el registro primero para poder adjuntar documentos de respaldo.
      </Alert>
    )
  }

  return (
    <Stack gap="sm">
      <Text size="sm" fw={600}>Documentos de respaldo</Text>

      {/* DataState como compuerta: la lista es pequeña y su estado vacío cabe
          en una línea, pero el error necesitaba dejar de verse como «no hay
          adjuntos». */}
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los documentos de respaldo"
        errorHint="No quiere decir que el registro no tenga evidencia adjunta: no se pudo consultar."
        onRetry={() => refetch()}
        skeletonRows={2}
      >
        {documentos.length === 0 ? (
          <Text size="xs" c="dimmed">Sin documentos adjuntos todavía.</Text>
        ) : documentos.map((doc: DocumentoSso) => (
          <Group key={doc.id} justify="space-between" wrap="nowrap" gap="xs">
            <Box style={{ minWidth: 0, flex: 1 }}>
              <Text size="sm" truncate>{doc.nombre}</Text>
              <Text size="xs" c="dimmed">
                {formatFecha(doc.created_at)} · {formatTamano(doc.tamano_bytes)}
              </Text>
            </Box>
            <Group gap={4} wrap="nowrap">
              <ActionIcon
                variant="subtle"
                loading={descargar.isPending}
                onClick={() => descargar.mutate(doc.id)}
                aria-label="Descargar"
              >
                <IconDownload size={16} />
              </ActionIcon>
              {puedeGestionar && (
                <ActionIcon
                  variant="subtle"
                  color="red"
                  onClick={() => confirmar({
                    title:   'Eliminar documento',
                    message: <>Se eliminará el documento <b>{doc.nombre}</b>. No se puede deshacer.</>,
                    destructiva: true,
                    onConfirm: () => eliminar.mutate(doc.id),
                  })}
                  aria-label="Eliminar"
                >
                  <IconTrash size={16} />
                </ActionIcon>
              )}
            </Group>
          </Group>
        ))}
      </DataState>

      {puedeGestionar && <SubirDocumentoSsoForm subir={subir} />}
    </Stack>
  )
}
