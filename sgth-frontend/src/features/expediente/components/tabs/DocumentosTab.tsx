'use client'

import { Stack, Group, Text, Badge, Button,
         ActionIcon, Tooltip, Divider } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconDownload, IconTrash,
         IconPaperclip } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useDocumentos } from '../../hooks/useDocumentos'
import { useDocumentoMutations } from '../../hooks/useDocumentoMutations'
import { DocumentoModal } from '../DocumentoModal'
import { expedienteService } from '../../services/expedienteService'
import type { DocumentoServidor } from '@/types/api'

const TIPO_LABELS: Record<string, string> = {
  cedula:            'Cédula de identidad',
  papeleta_votacion: 'Papeleta de votación',
  pasaporte:         'Pasaporte',
  titulo_academico:  'Título académico',
  certificado:       'Certificado',
  contrato:          'Contrato',
  nombramiento:      'Nombramiento',
  resolucion:        'Resolución',
  declaracion:       'Declaración juramentada',
  otro:              'Otro',
}

const GRUPOS: Record<string, string[]> = {
  'Identificación': ['cedula', 'papeleta_votacion', 'pasaporte'],
  'Académico':      ['titulo_academico', 'certificado'],
  'Laboral':        ['contrato', 'nombramiento', 'resolucion'],
  'Declaraciones':  ['declaracion'],
  'Otros':          ['otro'],
}

function formatBytes(bytes?: number): string {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

interface Props { servidorId: number }

export function DocumentosTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: documentos = [], isLoading } = useDocumentos(servidorId)
  const { eliminar } = useDocumentoMutations(servidorId)

  const docs = documentos as DocumentoServidor[]

  const handleDescargar = async (doc: DocumentoServidor) => {
    try {
      const blob = await expedienteService.descargarDocumento(
        servidorId, doc.id
      )
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = doc.nombre_archivo
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      // error manejado por el interceptor de axios
    }
  }

  if (!isLoading && docs.length === 0) {
    return (
      <Stack gap="md">
        <Group justify="flex-end">
          <Button size="xs" color="emerald" variant="light"
            leftSection={<IconPlus size={14} />} onClick={open}>
            Subir documento
          </Button>
        </Group>
        <EmptyState
          icon={IconPaperclip}
          title="Sin documentos"
          description="Sube los documentos del expediente del servidor."
        />
        <DocumentoModal
          opened={opened} onClose={close} servidorId={servidorId} />
      </Stack>
    )
  }

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Subir documento
        </Button>
      </Group>

      {Object.entries(GRUPOS).map(([grupo, tipos]) => {
        const items = docs.filter(d => tipos.includes(d.tipo_documento))
        if (items.length === 0) return null
        return (
          <Stack key={grupo} gap="xs">
            <Divider label={grupo} labelPosition="left" />
            {items.map(doc => (
              <Group key={doc.id} justify="space-between"
                p="xs" style={{
                  borderRadius: 8,
                  border: '1px solid var(--mantine-color-default-border)',
                }}>
                <Stack gap={2}>
                  <Text size="sm" fw={500}>
                    {TIPO_LABELS[doc.tipo_documento] ?? doc.tipo_documento}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {doc.nombre_archivo} · {formatBytes(doc.tamanio_bytes)}
                    {doc.subido_por?.usuario_ti
                      ? ` · ${doc.subido_por.usuario_ti}`
                      : ''}
                    {doc.created_at ? ` · ${doc.created_at}` : ''}
                  </Text>
                </Stack>
                <Group gap="xs">
                  {doc.fecha_vencimiento && (
                    <Badge size="xs" color="orange" variant="light">
                      Vence: {doc.fecha_vencimiento}
                    </Badge>
                  )}
                  <Tooltip label="Descargar" withArrow>
                    <ActionIcon variant="subtle" color="blue"
                      onClick={() => handleDescargar(doc)}>
                      <IconDownload size={14} />
                    </ActionIcon>
                  </Tooltip>
                  <Tooltip label="Eliminar" withArrow>
                    <ActionIcon variant="subtle" color="red"
                      onClick={() => {
                        if (confirm('¿Eliminar este documento?'))
                          eliminar.mutate(doc.id)
                      }}>
                      <IconTrash size={14} />
                    </ActionIcon>
                  </Tooltip>
                </Group>
              </Group>
            ))}
          </Stack>
        )
      })}

      <DocumentoModal
        opened={opened} onClose={close} servidorId={servidorId} />
    </Stack>
  )
}
