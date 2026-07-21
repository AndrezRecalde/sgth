'use client'

import { useState } from 'react'
import { Box, Stack, Group, Text, TextInput, Button, ActionIcon, Alert, Loader } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconUpload, IconX, IconFile, IconDownload, IconTrash, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDocumentosSso, useDocumentoSsoMutations } from '../hooks/useDocumentosSso'
import { formatFecha } from '../utils/fecha'
import type { TipoDocumentableSso, DocumentoSso } from '../services/documentoSsoService'

const MIMES_ACEPTADOS = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]

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
  const contained = useContainedInput()
  const { data: documentos = [], isLoading } = useDocumentosSso(tipo, documentableId)
  const { subir, eliminar, descargar } = useDocumentoSsoMutations(tipo, documentableId)

  const [archivo, setArchivo] = useState<File | null>(null)
  const [nombre, setNombre] = useState('')
  const [archivoError, setArchivoError] = useState('')

  if (!documentableId) {
    return (
      <Alert icon={<IconAlertCircle size={16} />} color="blue" variant="light">
        Guarde el registro primero para poder adjuntar documentos de respaldo.
      </Alert>
    )
  }

  const handleSubir = () => {
    if (!archivo) {
      setArchivoError('Seleccione un archivo para subir')
      return
    }
    if (!nombre.trim()) {
      setArchivoError('Indique un nombre para el documento')
      return
    }
    subir.mutateAsync({ nombre: nombre.trim(), archivo }).then(() => {
      setArchivo(null)
      setNombre('')
      setArchivoError('')
    }).catch(() => {})
  }

  return (
    <Stack gap="sm">
      <Text size="sm" fw={600}>Documentos de respaldo</Text>

      {isLoading && <Loader size="sm" />}

      {!isLoading && documentos.length === 0 && (
        <Text size="xs" c="dimmed">Sin documentos adjuntos todavía.</Text>
      )}

      {documentos.map((doc: DocumentoSso) => (
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
              color="emerald"
              loading={descargar.isPending}
              onClick={() => descargar.mutate(doc.id)}
              aria-label="Descargar"
            >
              <IconDownload size={16} />
            </ActionIcon>
            <ActionIcon
              variant="subtle"
              color="red"
              onClick={() => {
                if (confirm(`¿Eliminar el documento "${doc.nombre}"?`)) {
                  eliminar.mutate(doc.id)
                }
              }}
              aria-label="Eliminar"
            >
              <IconTrash size={16} />
            </ActionIcon>
          </Group>
        </Group>
      ))}

      <TextInput
        label="Nombre del documento"
        placeholder="Ej: Acta de socialización, evidencia fotográfica..."
        size="xs"
        {...contained}
        value={nombre}
        onChange={(e) => setNombre(e.currentTarget.value)}
      />

      <Dropzone
        onDrop={(files) => { setArchivo(files[0]); setArchivoError('') }}
        onReject={() => setArchivoError('Archivo no válido')}
        maxSize={10 * 1024 * 1024}
        accept={MIMES_ACEPTADOS}
      >
        <Group justify="center" gap="md" mih={60}>
          <Dropzone.Accept>
            <IconUpload size={22} color="var(--mantine-color-emerald-6)" />
          </Dropzone.Accept>
          <Dropzone.Reject>
            <IconX size={22} color="var(--mantine-color-red-6)" />
          </Dropzone.Reject>
          <Dropzone.Idle>
            <IconFile size={22} color="var(--mantine-color-dimmed)" />
          </Dropzone.Idle>
          <Text size="xs" c={archivo ? 'emerald' : 'dimmed'}>
            {archivo ? archivo.name : 'Arrastre el archivo aquí o haga clic (PDF, DOC, JPG, PNG — máx. 10MB)'}
          </Text>
        </Group>
      </Dropzone>
      {archivoError && <Text size="xs" c="red">{archivoError}</Text>}

      <Button
        size="xs"
        variant="light"
        color="emerald"
        leftSection={<IconUpload size={14} />}
        loading={subir.isPending}
        onClick={handleSubir}
      >
        Subir documento
      </Button>
    </Stack>
  )
}
