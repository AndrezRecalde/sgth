'use client'

import { Button, Group, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPaperclip, IconPlus } from '@tabler/icons-react'
import { DataState, SgthTable, notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { useDocumentos } from '../../hooks/useDocumentos'
import { useDocumentoMutations } from '../../hooks/useDocumentoMutations'
import { expedienteService } from '../../services/expedienteService'
import { getDocumentosColumns } from '../documentos.columns'
import { DocumentoModal } from '../DocumentoModal'
import type { DocumentoServidor } from '@/types/api'

interface Props { servidorId: number }

export function DocumentosTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: documentos = [], isLoading, error } = useDocumentos(servidorId)
  const { eliminar } = useDocumentoMutations(servidorId)

  const descargar = async (doc: DocumentoServidor) => {
    try {
      guardarArchivo(
        await expedienteService.descargarDocumento(servidorId, doc.id),
        doc.nombre_archivo,
      )
    } catch (e) {
      // El interceptor de axios solo atiende el 401: sin esto, un fallo
      // (archivo borrado del disco, sin permiso) no decía nada.
      notificar.error('No se pudo descargar el documento', getApiErrorMessage(e))
    }
  }

  const columns = getDocumentosColumns({
    onDescargar: descargar,
    onDelete: (id) => eliminar.mutate(id),
  })

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button size="xs" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Subir documento
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        empty={documentos.length === 0}
        skeletonRows={3}
        emptyProps={{
          icon: IconPaperclip,
          title: 'Sin documentos',
          description: 'Sube los documentos del expediente del servidor.',
        }}
      >
        <SgthTable records={documentos} columns={columns} minHeight={100} />
      </DataState>

      <DocumentoModal opened={opened} onClose={close} servidorId={servidorId} />
    </Stack>
  )
}
