'use client'

import { confirmar, DataState } from '@/components/ui'
import { useState } from 'react'
import { Box, Stack, Group, Text, TextInput, Button, ActionIcon, Alert } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconUpload, IconX, IconFile, IconDownload, IconTrash, IconAlertCircle } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDocumentosSso, useDocumentoSsoMutations } from '../hooks/useDocumentosSso'
import { formatFecha } from '@/lib/fecha'
import type { TipoDocumentableSso, DocumentoSso } from '../services/documentoSsoService'
import { erroresDeCampo } from '@/lib/erroresDeCampo'

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
  const { data: documentos = [], isLoading, error, refetch } = useDocumentosSso(tipo, documentableId)
  const { subir, eliminar, descargar } = useDocumentoSsoMutations(tipo, documentableId)

  // Descargar la evidencia es lectura; adjuntarla y borrarla, no.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const [archivo, setArchivo] = useState<File | null>(null)
  const [nombre, setNombre] = useState('')
  // Dos ranuras y no una: había un solo `archivoError` para los dos campos,
  // así que un error del nombre se pintaba debajo de la zona de carga. Este
  // panel todavia no usa React Hook Form —va en el PR que convierte las tres
  // pantallas de `useState` del módulo—, pero el 422 del backend ya aterriza
  // donde corresponde.
  const [errorNombre, setErrorNombre] = useState('')
  const [errorArchivo, setErrorArchivo] = useState('')

  if (!documentableId) {
    return (
      <Alert icon={<IconAlertCircle size={16} />} color="ocean" variant="light">
        Guarde el registro primero para poder adjuntar documentos de respaldo.
      </Alert>
    )
  }

  const handleSubir = () => {
    setErrorNombre('')
    setErrorArchivo('')

    if (!archivo) {
      setErrorArchivo('Seleccione un archivo para subir')
      return
    }
    if (!nombre.trim()) {
      setErrorNombre('Indique un nombre para el documento')
      return
    }
    subir.mutateAsync({ nombre: nombre.trim(), archivo }).then(() => {
      setArchivo(null)
      setNombre('')
    }).catch((error) => {
      // El 422 del backend a su campo: rechaza por tipo de archivo, por tamano
      // (10 MB) y por la longitud del nombre, y los tres salían como la misma
      // notificación genérica.
      const campos = erroresDeCampo(error)
      if (! campos) return // el hook ya lo notificó
      if (campos.nombre) setErrorNombre(campos.nombre)
      const delArchivo = campos.archivo ?? campos.documentable_type ?? campos.documentable_id
      if (delArchivo) setErrorArchivo(delArchivo)
    })
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

      {puedeGestionar && (<>
        <TextInput
          label="Nombre del documento"
          placeholder="Ej: Acta de socialización, evidencia fotográfica..."
          size="xs"
          {...contained}
          value={nombre}
          onChange={(e) => setNombre(e.currentTarget.value)}
          error={errorNombre || undefined}
        />

        <Dropzone
          onDrop={(files) => { setArchivo(files[0]); setErrorArchivo('') }}
          // El motivo, no «Archivo no válido»: la zona rechaza por tipo y
          // por tamaño, y sin decir cuál se prueba a ciegas.
          onReject={() => setErrorArchivo(
            'El archivo no es válido: solo PDF, DOC, JPG o PNG, y hasta 10 MB.',
          )}
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
        {errorArchivo && <Text size="xs" c="red">{errorArchivo}</Text>}

        <Button
          size="xs"
          variant="light"
          leftSection={<IconUpload size={14} />}
          loading={subir.isPending}
          onClick={handleSubir}
        >
          Subir documento
        </Button>
      </>)}
    </Stack>
  )
}
